import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser } from "@/lib/auth";
import { db } from "@/lib/db";

export async function POST(
  req: NextRequest,
  { params }: { params: { id: string } }
) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Bạn cần đăng nhập." }, { status: 401 });
    }

    const postId = parseInt(params.id);
    if (isNaN(postId)) {
      return NextResponse.json({ error: "ID bài viết không hợp lệ." }, { status: 400 });
    }

    const { reactionType } = await req.json(); // "like", "love", "haha", "wow", "sad", "angry"
    const validReactions = ["like", "love", "haha", "wow", "sad", "angry"];
    
    const type = reactionType && validReactions.includes(reactionType) ? reactionType : "like";

    // Tìm bài viết để chắc chắn nó tồn tại
    const post = await db.post.findUnique({
      where: { id: postId },
    });

    if (!post) {
      return NextResponse.json({ error: "Bài viết không tồn tại." }, { status: 404 });
    }

    // Kiểm tra xem user đã react bài viết này chưa
    const existingReaction = await db.reaction.findFirst({
      where: {
        userId: user.id,
        postId: postId,
      },
    });

    let action = "";

    if (existingReaction) {
      if (existingReaction.reactionType === type) {
        // Hủy thả cảm xúc nếu thả trùng loại cũ
        await db.reaction.delete({
          where: { id: existingReaction.id },
        });
        action = "removed";
      } else {
        // Cập nhật loại cảm xúc mới
        await db.reaction.update({
          where: { id: existingReaction.id },
          data: { reactionType: type },
        });
        action = "updated";
      }
    } else {
      // Thả cảm xúc mới
      await db.reaction.create({
        data: {
          userId: user.id,
          postId: postId,
          reactionType: type,
        },
      });
      action = "added";

      // Tạo thông báo cho tác giả bài viết
      if (post.userId !== user.id) {
        await db.notification.create({
          data: {
            userId: post.userId,
            senderId: user.id,
            type: "reaction",
            targetId: postId,
          },
        });
      }
    }

    // Lấy số lượng reactions hiện tại và danh sách các reactions
    const allReactions = await db.reaction.findMany({
      where: { postId: postId },
      select: { reactionType: true, userId: true },
    });

    return NextResponse.json({
      message: "Thao tác thành công",
      action,
      reactionsCount: allReactions.length,
      reactions: allReactions,
    });
  } catch (error) {
    console.error("React Post Error:", error);
    return NextResponse.json({ error: "Lỗi xử lý cảm xúc" }, { status: 500 });
  }
}
