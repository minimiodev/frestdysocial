import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser } from "@/lib/auth";
import { db } from "@/lib/db";
import { cookies } from "next/headers";

export async function DELETE(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Bạn cần đăng nhập." }, { status: 401 });
    }

    // 1. Thực hiện xóa tài khoản trong Database
    // Nhờ onDelete: Cascade, Prisma và PostgreSQL tự động dọn dẹp các bảng phụ liên kết
    await db.user.delete({
      where: { id: user.id },
    });

    // 2. Xóa các Session Cookie của người dùng
    const cookieStore = cookies();
    cookieStore.set("frest_session", "", { path: "/", maxAge: 0 });
    cookieStore.set("frest_identity", "", { path: "/", maxAge: 0 });

    return NextResponse.json({
      message: "Tài khoản của bạn đã được xóa vĩnh viễn thành công.",
    }, { status: 200 });

  } catch (error: any) {
    console.error("Delete Account Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống khi xóa tài khoản." }, { status: 500 });
  }
}
