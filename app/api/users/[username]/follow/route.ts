import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser } from "@/lib/auth";
import { db } from "@/lib/db";

export async function POST(
  req: NextRequest,
  { params }: { params: { username: string } }
) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Bạn cần đăng nhập." }, { status: 401 });
    }

    const targetUsername = params.username.toLowerCase();

    // Tìm người dùng mục tiêu
    const targetUser = await db.user.findUnique({
      where: { username: targetUsername },
    });

    if (!targetUser) {
      return NextResponse.json({ error: "Người dùng không tồn tại." }, { status: 404 });
    }

    if (targetUser.id === user.id) {
      return NextResponse.json({ error: "Bạn không thể tự theo dõi chính mình." }, { status: 400 });
    }

    // Kiểm tra xem đã theo dõi chưa
    const existingFollow = await db.follow.findUnique({
      where: {
        followerId_followedId: {
          followerId: user.id,
          followedId: targetUser.id,
        },
      },
    });

    let following = false;

    if (existingFollow) {
      // Đã follow -> Unfollow
      await db.follow.delete({
        where: {
          followerId_followedId: {
            followerId: user.id,
            followedId: targetUser.id,
          },
        },
      });
      following = false;
    } else {
      // Chưa follow -> Follow
      await db.follow.create({
        data: {
          followerId: user.id,
          followedId: targetUser.id,
        },
      });
      following = true;

      // Tạo thông báo cho đối phương
      await db.notification.create({
        data: {
          userId: targetUser.id,
          senderId: user.id,
          type: "follow",
        },
      });
    }

    return NextResponse.json({
      message: following ? "Đã theo dõi người dùng." : "Đã hủy theo dõi người dùng.",
      following,
    });
  } catch (error) {
    console.error("Follow User Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}
