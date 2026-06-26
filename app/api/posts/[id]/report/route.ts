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
      return NextResponse.json({ error: "Bạn cần đăng nhập để gửi báo cáo." }, { status: 401 });
    }

    const postId = parseInt(params.id);
    if (isNaN(postId)) {
      return NextResponse.json({ error: "ID bài viết không hợp lệ." }, { status: 400 });
    }

    const { reason, details } = await req.json();

    if (!reason || !reason.trim()) {
      return NextResponse.json({ error: "Vui lòng chọn lý do báo cáo vi phạm." }, { status: 400 });
    }

    // 1. Kiểm tra bài viết tồn tại
    const post = await db.post.findUnique({
      where: { id: postId },
    });

    if (!post) {
      return NextResponse.json({ error: "Bài viết không tồn tại." }, { status: 404 });
    }

    // 2. Tạo bản ghi báo cáo
    const report = await db.report.create({
      data: {
        reporterId: user.id,
        reportedPostId: postId,
        reportedUserId: post.userId, // Người viết bài đăng
        reason: reason.trim(),
        details: details || "",
        status: "pending",
      },
    });

    return NextResponse.json({
      message: "Cảm ơn bạn! Báo cáo của bạn đã được ghi nhận và đang chờ ban quản trị xét duyệt.",
      report,
    }, { status: 201 });

  } catch (error: any) {
    console.error("Report Post Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống khi gửi báo cáo." }, { status: 500 });
  }
}
