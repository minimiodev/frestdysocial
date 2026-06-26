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

    const post = await db.post.findUnique({
      where: { id: postId },
    });

    if (!post) {
      return NextResponse.json({ error: "Bài viết không tồn tại." }, { status: 404 });
    }

    // Kiểm tra xem đã lưu bookmark chưa
    const existingBookmark = await db.bookmark.findUnique({
      where: {
        userId_postId: {
          userId: user.id,
          postId: postId,
        },
      },
    });

    let bookmarked = false;

    if (existingBookmark) {
      // Hủy bookmark
      await db.bookmark.delete({
        where: {
          userId_postId: {
            userId: user.id,
            postId: postId,
          },
        },
      });
      bookmarked = false;
    } else {
      // Lưu bookmark
      await db.bookmark.create({
        data: {
          userId: user.id,
          postId: postId,
        },
      });
      bookmarked = true;
    }

    return NextResponse.json({
      message: bookmarked ? "Đã lưu bài viết vào mục dấu trang." : "Đã bỏ lưu bài viết.",
      bookmarked,
    });
  } catch (error) {
    console.error("Bookmark Post Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}
