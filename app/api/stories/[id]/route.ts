import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser } from "@/lib/auth";
import { db } from "@/lib/db";

/**
 * POST: Đăng ký lượt xem story hoặc thả cảm xúc cho story
 * Body: { action: "view" } hoặc { action: "react", reactionType: "love" }
 */
export async function POST(
  req: NextRequest,
  { params }: { params: { id: string } }
) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Chưa đăng nhập" }, { status: 401 });
    }

    const storyId = parseInt(params.id);
    if (isNaN(storyId)) {
      return NextResponse.json({ error: "ID tin không hợp lệ." }, { status: 400 });
    }

    const body = await req.json();
    const { action, reactionType } = body;

    const story = await db.story.findUnique({
      where: { id: storyId },
    });

    if (!story) {
      return NextResponse.json({ error: "Story không tồn tại." }, { status: 404 });
    }

    if (action === "view") {
      // Nếu là chính chủ xem thì không ghi nhận view mới
      if (story.userId === user.id) {
        return NextResponse.json({ success: true, message: "Chủ sở hữu xem story" });
      }

      // Kiểm tra xem đã view chưa
      const existingView = await db.storyView.findFirst({
        where: {
          storyId: storyId,
          viewerId: user.id,
        },
      });

      if (!existingView) {
        await db.storyView.create({
          data: {
            storyId: storyId,
            viewerId: user.id,
          },
        });
      }

      return NextResponse.json({ success: true, message: "Đã ghi nhận lượt xem." });
    } else if (action === "react") {
      const type = reactionType || "like";

      // Kiểm tra xem đã react story này chưa
      const existingReact = await db.storyReaction.findFirst({
        where: {
          storyId: storyId,
          userId: user.id,
        },
      });

      if (existingReact) {
        if (existingReact.reactionType === type) {
          // Hủy react
          await db.storyReaction.delete({
            where: { id: existingReact.id },
          });
          return NextResponse.json({ success: true, message: "Đã hủy cảm xúc.", reacted: false });
        } else {
          // Cập nhật loại react
          await db.storyReaction.update({
            where: { id: existingReact.id },
            data: { reactionType: type },
          });
          return NextResponse.json({ success: true, message: "Đã cập nhật cảm xúc.", reacted: true });
        }
      } else {
        // Tạo react mới
        await db.storyReaction.create({
          data: {
            storyId: storyId,
            userId: user.id,
            reactionType: type,
          },
        });

        // Bắn thông báo cho chủ story
        if (story.userId !== user.id) {
          await db.notification.create({
            data: {
              userId: story.userId,
              senderId: user.id,
              type: "story_reaction",
              targetId: storyId,
            },
          });
        }

        return NextResponse.json({ success: true, message: "Đã bày tỏ cảm xúc.", reacted: true });
      }
    }

    return NextResponse.json({ error: "Hành động không hợp lệ." }, { status: 400 });
  } catch (error) {
    console.error("Story Action error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}
