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

    // 1. Tìm người dùng mục tiêu
    const targetUser = await db.user.findUnique({
      where: { username: targetUsername },
    });

    if (!targetUser) {
      return NextResponse.json({ error: "Người dùng không tồn tại." }, { status: 404 });
    }

    if (targetUser.id === user.id) {
      return NextResponse.json({ error: "Bạn không thể chặn chính mình." }, { status: 400 });
    }

    // 2. Kiểm tra xem đã chặn chưa
    const existingBlock = await db.block.findUnique({
      where: {
        blockerId_blockedId: {
          blockerId: user.id,
          blockedId: targetUser.id,
        },
      },
    });

    let blocked = false;

    if (existingBlock) {
      // Đã chặn -> Hủy chặn (Unblock)
      await db.block.delete({
        where: {
          blockerId_blockedId: {
            blockerId: user.id,
            blockedId: targetUser.id,
          },
        },
      });
      blocked = false;
    } else {
      // Chưa chặn -> Chặn (Block)
      await db.$transaction(async (tx) => {
        // Tạo bản ghi chặn
        await tx.block.create({
          data: {
            blockerId: user.id,
            blockedId: targetUser.id,
          },
        });

        // Hủy theo dõi hai chiều (nếu có)
        await tx.follow.deleteMany({
          where: {
            OR: [
              { followerId: user.id, followedId: targetUser.id },
              { followerId: targetUser.id, followedId: user.id },
            ],
          },
        });
      });
      blocked = true;
    }

    return NextResponse.json({
      message: blocked ? "Đã chặn người dùng này." : "Đã hủy chặn người dùng này.",
      blocked,
    });
  } catch (error: any) {
    console.error("Block User Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}
