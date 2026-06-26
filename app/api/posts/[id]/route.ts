import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser } from "@/lib/auth";
import { db } from "@/lib/db";

export const dynamic = "force-dynamic";

/**
 * PUT: Cập nhật bài viết (Sửa nội dung hoặc Ghim/Bỏ ghim)
 */
export async function PUT(
  req: NextRequest,
  { params }: { params: { id: string } }
) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Bạn cần đăng nhập để sửa bài viết." }, { status: 401 });
    }

    const postId = parseInt(params.id);
    if (isNaN(postId)) {
      return NextResponse.json({ error: "ID bài viết không hợp lệ." }, { status: 400 });
    }

    // Lấy thông tin bài viết và Trang liên quan
    const post = await db.post.findUnique({
      where: { id: postId },
      include: { page: true },
    });

    if (!post) {
      return NextResponse.json({ error: "Không tìm thấy bài viết." }, { status: 404 });
    }

    // Kiểm tra quyền sở hữu
    const isAuthor = post.userId === user.id;
    const isPageOwner = post.page && post.page.ownerId === user.id;

    if (!isAuthor && !isPageOwner) {
      return NextResponse.json({ error: "Bạn không có quyền chỉnh sửa bài viết này." }, { status: 403 });
    }

    const body = await req.json();
    const { content, isPinned } = body;

    const updateData: any = {};

    // 1. Cập nhật nội dung bài viết
    if (content !== undefined) {
      updateData.content = content;
    }

    // 2. Cập nhật trạng thái Ghim
    if (isPinned !== undefined) {
      updateData.isPinned = !!isPinned;

      // Nếu ghim bài viết này lên đầu trang, unpin toàn bộ các bài viết cũ của user/page này
      if (isPinned === true) {
        if (post.pageId) {
          await db.post.updateMany({
            where: { pageId: post.pageId, id: { not: postId } },
            data: { isPinned: false },
          });
        } else {
          await db.post.updateMany({
            where: { userId: post.userId, pageId: null, id: { not: postId } },
            data: { isPinned: false },
          });
        }
      }
    }

    // Cập nhật vào DB
    const updatedPost = await db.post.update({
      where: { id: postId },
      data: updateData,
      include: {
        user: {
          select: {
            id: true,
            username: true,
            fullName: true,
            avatarFilename: true,
            verificationType: true,
          },
        },
        page: {
          select: {
            id: true,
            pageName: true,
            pageUsername: true,
            avatarFilename: true,
            isVerified: true,
          },
        },
      },
    });

    return NextResponse.json({
      message: "Cập nhật bài viết thành công!",
      post: updatedPost,
    });

  } catch (error: any) {
    console.error("Update Post Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống khi cập nhật bài viết." }, { status: 500 });
  }
}

/**
 * DELETE: Xóa bài viết
 */
export async function DELETE(
  req: NextRequest,
  { params }: { params: { id: string } }
) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Bạn cần đăng nhập để xóa bài viết." }, { status: 401 });
    }

    const postId = parseInt(params.id);
    if (isNaN(postId)) {
      return NextResponse.json({ error: "ID bài viết không hợp lệ." }, { status: 400 });
    }

    // Lấy thông tin bài viết và Trang liên quan
    const post = await db.post.findUnique({
      where: { id: postId },
      include: { page: true },
    });

    if (!post) {
      return NextResponse.json({ error: "Không tìm thấy bài viết." }, { status: 404 });
    }

    // Kiểm tra quyền sở hữu
    const isAuthor = post.userId === user.id;
    const isPageOwner = post.page && post.page.ownerId === user.id;

    if (!isAuthor && !isPageOwner) {
      return NextResponse.json({ error: "Bạn không có quyền xóa bài viết này." }, { status: 403 });
    }

    // Thực hiện xóa bài viết trong DB (Prisma cascade delete các quan hệ liên quan như replies, reactions...)
    await db.post.delete({
      where: { id: postId },
    });

    return NextResponse.json({
      message: "Xóa bài viết thành công!",
    });

  } catch (error: any) {
    console.error("Delete Post Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống khi xóa bài viết." }, { status: 500 });
  }
}
