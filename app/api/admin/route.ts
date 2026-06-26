import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedAdmin } from "@/lib/auth";
import { db } from "@/lib/db";

export const dynamic = "force-dynamic";

/**
 * GET: Lấy danh sách dữ liệu quản trị
 */
export async function GET(req: NextRequest) {
  try {
    const admin = await getAuthenticatedAdmin(req);
    if (!admin) {
      return NextResponse.json({ error: "Không có quyền truy cập Admin." }, { status: 403 });
    }

    const url = new URL(req.url);
    const type = url.searchParams.get("type") || "all"; // "names", "ages", "reports", "complaints", "users", "pages", "posts", "settings", "all"

    const data: any = {};

    if (type === "names" || type === "all") {
      data.nameRequests = await db.user.findMany({
        where: { nameChangeStatus: "pending" },
        select: {
          id: true,
          username: true,
          fullName: true,
          firstName: true,
          middleName: true,
          lastName: true,
          pendingFirstName: true,
          pendingMiddleName: true,
          pendingLastName: true,
          pendingNameDisplayOrder: true,
        },
        orderBy: { createdAt: "desc" },
      });
    }

    if (type === "ages" || type === "all") {
      data.ageRequests = await db.user.findMany({
        where: { ageVerificationStatus: "pending" },
        select: {
          id: true,
          username: true,
          fullName: true,
          dob: true,
          idProofFilename: true,
          ageVerificationStatus: true,
        },
        orderBy: { createdAt: "desc" },
      });
    }

    if (type === "reports" || type === "all") {
      data.reports = await db.report.findMany({
        where: { status: "pending" },
        include: {
          reporter: { select: { username: true } },
          reportedUser: { select: { username: true } },
          reportedPost: { select: { id: true, content: true } },
        },
        orderBy: { createdAt: "desc" },
      });
    }

    if (type === "complaints" || type === "all") {
      data.complaints = await db.copyrightComplaint.findMany({
        where: { status: "pending" },
        orderBy: { createdAt: "desc" },
      });
    }

    if (type === "users" || type === "all") {
      data.users = await db.user.findMany({
        select: {
          id: true,
          username: true,
          fullName: true,
          email: true,
          phoneNumber: true,
          avatarFilename: true,
          verificationType: true,
          status: true,
          createdAt: true,
        },
        orderBy: { createdAt: "desc" },
        take: 150, // Lấy tối đa 150 user mới nhất để hiển thị mượt mà
      });
    }

    if (type === "pages" || type === "all") {
      data.pages = await db.page.findMany({
        include: {
          owner: { select: { username: true } }
        },
        orderBy: { createdAt: "desc" },
        take: 100,
      });
    }

    if (type === "posts" || type === "all") {
      data.posts = await db.post.findMany({
        include: {
          user: { select: { username: true, fullName: true } }
        },
        orderBy: { createdAt: "desc" },
        take: 100,
      });
    }

    if (type === "settings" || type === "all") {
      data.settings = await db.setting.findMany();
    }

    return NextResponse.json(data);
  } catch (error) {
    console.error("Admin GET error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}

/**
 * POST: Xử lý hành động quản trị
 */
export async function POST(req: NextRequest) {
  try {
    const admin = await getAuthenticatedAdmin(req);
    if (!admin) {
      return NextResponse.json({ error: "Không có quyền truy cập Admin." }, { status: 403 });
    }

    const body = await req.json();
    const { actionType, targetUserId, targetId, approve } = body;

    // 1. Phê duyệt đổi tên hiển thị
    if (actionType === "name_approve") {
      const user = await db.user.findUnique({
        where: { id: parseInt(targetUserId) },
      });

      if (!user || user.nameChangeStatus !== "pending") {
        return NextResponse.json({ error: "Yêu cầu đổi tên không tồn tại." }, { status: 404 });
      }

      if (approve) {
        const oldName = user.fullName;
        const newFirst = user.pendingFirstName || "";
        const newMid = user.pendingMiddleName || "";
        const newLast = user.pendingLastName || "";
        const displayOrder = user.pendingNameDisplayOrder || "last_middle_first";

        let newFullName = "";
        if (displayOrder === "last_middle_first") {
          newFullName = `${newLast} ${newMid} ${newFirst}`.replace(/\s+/g, " ").trim();
        } else {
          newFullName = `${newFirst} ${newMid} ${newLast}`.replace(/\s+/g, " ").trim();
        }

        await db.$transaction([
          db.user.update({
            where: { id: user.id },
            data: {
              firstName: newFirst,
              middleName: newMid,
              lastName: newLast,
              fullName: newFullName,
              nameDisplayOrder: displayOrder,
              nameChangeStatus: "approved",
              pendingFirstName: null,
              pendingMiddleName: null,
              pendingLastName: null,
              pendingNameDisplayOrder: null,
            },
          }),
          db.nameHistory.create({
            data: {
              userId: user.id,
              oldName: oldName || user.username,
              newName: newFullName,
            },
          }),
          db.notification.create({
            data: {
              userId: user.id,
              type: "name_approved",
            },
          }),
        ]);

        return NextResponse.json({ message: "Đã phê duyệt yêu cầu đổi tên." });
      } else {
        await db.user.update({
          where: { id: user.id },
          data: {
            nameChangeStatus: "rejected",
            pendingFirstName: null,
            pendingMiddleName: null,
            pendingLastName: null,
            pendingNameDisplayOrder: null,
          },
        });

        await db.notification.create({
          data: {
            userId: user.id,
            type: "name_rejected",
          },
        });

        return NextResponse.json({ message: "Đã từ chối yêu cầu đổi tên." });
      }
    }

    // 2. Phê duyệt xác minh độ tuổi
    if (actionType === "age_approve") {
      const user = await db.user.findUnique({
        where: { id: parseInt(targetUserId) },
      });

      if (!user || user.ageVerificationStatus !== "pending") {
        return NextResponse.json({ error: "Yêu cầu xác minh độ tuổi không tồn tại." }, { status: 404 });
      }

      if (approve) {
        await db.user.update({
          where: { id: user.id },
          data: {
            ageVerificationStatus: "verified",
            isAdult: true,
          },
        });

        await db.notification.create({
          data: {
            userId: user.id,
            type: "age_verified",
          },
        });

        return NextResponse.json({ message: "Đã xác minh tài khoản 18+ thành công." });
      } else {
        await db.user.update({
          where: { id: user.id },
          data: {
            ageVerificationStatus: "rejected",
            idProofFilename: null,
          },
        });

        await db.notification.create({
          data: {
            userId: user.id,
            type: "age_rejected",
          },
        });

        return NextResponse.json({ message: "Đã từ chối xác minh độ tuổi." });
      }
    }

    // 3. Xử lý báo cáo bài viết/người dùng
    if (actionType === "report_resolve") {
      const reportId = parseInt(targetId);
      const report = await db.report.findUnique({
        where: { id: reportId },
      });

      if (!report) {
        return NextResponse.json({ error: "Báo cáo không tồn tại." }, { status: 404 });
      }

      if (approve) {
        await db.report.update({
          where: { id: reportId },
          data: { status: "resolved" },
        });

        if (report.reportedPostId) {
          try {
            await db.post.delete({
              where: { id: report.reportedPostId },
            });
            return NextResponse.json({ message: "Đã duyệt báo cáo và xóa bài viết vi phạm thành công." });
          } catch (deleteError) {
            console.error("Lỗi khi xóa bài đăng vi phạm:", deleteError);
            return NextResponse.json({ error: "Duyệt báo cáo thành công nhưng không thể xóa bài viết." }, { status: 500 });
          }
        }

        return NextResponse.json({ message: "Đã duyệt báo cáo và đánh dấu xử lý." });
      } else {
        await db.report.update({
          where: { id: reportId },
          data: { status: "rejected" },
        });
        return NextResponse.json({ message: "Đã bác bỏ báo cáo." });
      }
    }

    // 4. Cấp/hủy tích xác minh cho User
    if (actionType === "update_user_verification") {
      const { verificationType } = body;
      const userId = parseInt(targetUserId);

      await db.user.update({
        where: { id: userId },
        data: {
          verificationType: verificationType || null,
        },
      });

      return NextResponse.json({ message: "Cập nhật tích xác minh thành viên thành công." });
    }

    // 5. Thay đổi trạng thái tài khoản User (active / suspended)
    if (actionType === "update_user_status") {
      const { status } = body;
      const userId = parseInt(targetUserId);

      await db.user.update({
        where: { id: userId },
        data: {
          status: status || "active",
        },
      });

      return NextResponse.json({ message: "Cập nhật trạng thái tài khoản thành công." });
    }

    // 6. Cấp/hủy tích xác minh cho Fanpage
    if (actionType === "update_page_verification") {
      const pageId = parseInt(targetId);
      const { isVerified } = body;

      await db.page.update({
        where: { id: pageId },
        data: {
          isVerified: !!isVerified,
          verificationType: isVerified ? "official" : null,
        },
      });

      return NextResponse.json({ message: "Cập nhật tích xác minh Fanpage thành công." });
    }

    // 7. Xóa Fanpage
    if (actionType === "delete_page") {
      const pageId = parseInt(targetId);

      await db.page.delete({
        where: { id: pageId },
      });

      return NextResponse.json({ message: "Đã xóa trang Fanpage thành công." });
    }

    // 8. Xóa bài đăng trực tiếp
    if (actionType === "delete_post") {
      const postId = parseInt(targetId);

      await db.post.delete({
        where: { id: postId },
      });

      return NextResponse.json({ message: "Đã xóa bài đăng thành công." });
    }

    // 9. Cập nhật settings hệ thống
    if (actionType === "update_setting") {
      const { keyName, keyValue } = body;

      await db.setting.upsert({
        where: { keyName: keyName },
        update: { keyValue: keyValue },
        create: { keyName: keyName, keyValue: keyValue },
      });

      return NextResponse.json({ message: `Cập nhật cấu hình "${keyName}" thành công.` });
    }

    return NextResponse.json({ error: "Hành động không hợp lệ." }, { status: 400 });
  } catch (error) {
    console.error("Admin POST error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}
