import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser } from "@/lib/auth";
import { db } from "@/lib/db";

export async function POST(
  req: NextRequest,
  { params }: { params: { pageUsername: string } }
) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Bạn cần đăng nhập." }, { status: 401 });
    }

    const pageUsername = params.pageUsername.toLowerCase();

    // 1. Tìm trang mục tiêu
    const page = await db.page.findUnique({
      where: { pageUsername },
    });

    if (!page) {
      return NextResponse.json({ error: "Trang không tồn tại." }, { status: 404 });
    }

    // 2. Kiểm tra xem user đã follow trang này chưa
    const existingFollow = await db.pageFollow.findUnique({
      where: {
        userId_pageId: {
          userId: user.id,
          pageId: page.id,
        },
      },
    });

    let following = false;

    if (existingFollow) {
      // Đã follow -> Unfollow page
      await db.pageFollow.delete({
        where: {
          userId_pageId: {
            userId: user.id,
            pageId: page.id,
          },
        },
      });
      following = false;
    } else {
      // Chưa follow -> Follow page
      await db.pageFollow.create({
        data: {
          userId: user.id,
          pageId: page.id,
        },
      });
      following = true;

      // Tạo thông báo cho chủ sở hữu trang (owner)
      if (page.ownerId !== user.id) {
        await db.notification.create({
          data: {
            userId: page.ownerId,
            senderId: user.id,
            type: "page_follow",
            targetId: page.id,
          },
        });
      }
    }

    return NextResponse.json({
      message: following ? "Đã theo dõi trang thành công." : "Đã hủy theo dõi trang.",
      following,
    });
  } catch (error: any) {
    console.error("Follow Page Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}
