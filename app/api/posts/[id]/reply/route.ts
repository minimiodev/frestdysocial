import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser } from "@/lib/auth";
import { db } from "@/lib/db";

/**
 * GET: Lấy toàn bộ bình luận của bài viết
 */
export async function GET(
  req: NextRequest,
  { params }: { params: { id: string } }
) {
  try {
    const postId = parseInt(params.id);
    if (isNaN(postId)) {
      return NextResponse.json({ error: "ID bài viết không hợp lệ." }, { status: 400 });
    }

    const replies = await db.reply.findMany({
      where: { postId: postId, parentReplyId: null }, // Lấy các bình luận gốc trước
      include: {
        user: {
          select: {
            id: true,
            username: true,
            fullName: true,
            avatarFilename: true,
          },
        },
        childReplies: {
          include: {
            user: {
              select: {
                id: true,
                username: true,
                fullName: true,
                avatarFilename: true,
              }
            }
          },
          orderBy: { createdAt: "asc" }
        },
        reactions: {
          select: {
            userId: true,
            reactionType: true,
          }
        }
      },
      orderBy: { createdAt: "desc" },
    });

    return NextResponse.json({ replies });
  } catch (error) {
    console.error("Get Replies Error:", error);
    return NextResponse.json({ error: "Lỗi lấy danh sách bình luận" }, { status: 500 });
  }
}

/**
 * POST: Viết bình luận mới hoặc phản hồi bình luận cũ
 */
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

    const { content, parentReplyId } = await req.json();
    if (!content || !content.trim()) {
      return NextResponse.json({ error: "Bình luận không được để trống." }, { status: 400 });
    }

    const post = await db.post.findUnique({
      where: { id: postId },
    });

    if (!post) {
      return NextResponse.json({ error: "Bài viết không tồn tại." }, { status: 404 });
    }

    const parentId = parentReplyId ? parseInt(parentReplyId) : null;

    const newReply = await db.$transaction(async (tx) => {
      // 1. Tạo reply mới
      const reply = await tx.reply.create({
        data: {
          postId: postId,
          userId: user.id,
          content: content.trim(),
          parentReplyId: parentId,
        },
        include: {
          user: {
            select: {
              id: true,
              username: true,
              fullName: true,
              avatarFilename: true,
            }
          }
        }
      });

      // 2. Tạo thông báo
      if (parentId) {
        // Nếu là trả lời bình luận của ai đó
        const parentReply = await tx.reply.findUnique({
          where: { id: parentId },
        });
        if (parentReply && parentReply.userId !== user.id) {
          await tx.notification.create({
            data: {
              userId: parentReply.userId,
              senderId: user.id,
              type: "reply_comment",
              targetId: reply.id,
            }
          });
        }
      } else {
        // Nếu là bình luận bài viết của người khác
        if (post.userId !== user.id) {
          await tx.notification.create({
            data: {
              userId: post.userId,
              senderId: user.id,
              type: "reply",
              targetId: reply.id,
            }
          });
        }
      }

      return reply;
    });

    return NextResponse.json({ message: "Bình luận thành công!", reply: newReply }, { status: 201 });
  } catch (error) {
    console.error("Create Reply Error:", error);
    return NextResponse.json({ error: "Lỗi bình luận" }, { status: 500 });
  }
}
