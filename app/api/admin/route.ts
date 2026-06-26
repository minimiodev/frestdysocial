import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedAdmin } from "@/lib/auth";
import { db } from "@/lib/db";

export const dynamic = "force-dynamic";

/**
 * GET: Lấy danh sách yêu cầu cần duyệt cho Admin
 */
export async function GET(req: NextRequest) {
  try {
    const admin = await getAuthenticatedAdmin(req);
    if (!admin) {
      return NextResponse.json({ error: "Không có quyền truy cập Admin." }, { status: 403 });
    }

    const url = new URL(req.url);
    const type = url.searchParams.get("type") || "all"; // "names", "ages", "reports", "complaints", "all"

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
      });
    }

    if (type === "complaints" || type === "all") {
      data.complaints = await db.copyrightComplaint.findMany({
        where: { status: "pending" },
      });
    }

    return NextResponse.json(data);
  } catch (error) {
    console.error("Admin GET error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}

/**
 * POST: Xử lý phê duyệt/từ chối của Admin
 */
export async function POST(req: NextRequest) {
  try {
    const admin = await getAuthenticatedAdmin(req);
    if (!admin) {
      return NextResponse.json({ error: "Không có quyền truy cập Admin." }, { status: 403 });
    }

    const { actionType, targetUserId, targetId, approve, reason } = await req.json();

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

        // Tạo fullName mới dựa trên display order
        let newFullName = "";
        if (displayOrder === "last_middle_first") {
          newFullName = `${newLast} ${newMid} ${newFirst}`.replace(/\s+/g, " ").trim();
        } else {
          newFullName = `${newFirst} ${newMid} ${newLast}`.replace(/\s+/g, " ").trim();
        }

        await db.$transaction([
          // Cập nhật tên mới của user
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
          // Ghi vào lịch sử đổi tên
          db.nameHistory.create({
            data: {
              userId: user.id,
              oldName: oldName || user.username,
              newName: newFullName,
            },
          }),
          // Gửi thông báo đến user
          db.notification.create({
            data: {
              userId: user.id,
              type: "name_approved",
            },
          }),
        ]);

        return NextResponse.json({ message: "Đã phê duyệt yêu cầu đổi tên." });
      } else {
        // Từ chối đổi tên
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

        // Bắn notification thông báo từ chối
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
      if (approve) {
        // Nếu duyệt báo cáo là chính xác, chuyển trạng thái report thành resolved
        await db.report.update({
          where: { id: reportId },
          data: { status: "resolved" },
        });

        // Ở đây có thể thêm logic phạt user bị report như block hoặc cảnh cáo
        return NextResponse.json({ message: "Đã duyệt báo cáo và đánh dấu xử lý." });
      } else {
        // Bác bỏ báo cáo
        await db.report.update({
          where: { id: reportId },
          data: { status: "rejected" },
        });
        return NextResponse.json({ message: "Đã bác bỏ báo cáo." });
      }
    }

    return NextResponse.json({ error: "Hành động không hợp lệ." }, { status: 400 });
  } catch (error) {
    console.error("Admin POST error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}
