import { NextRequest, NextResponse } from "next/server";
import { db } from "@/lib/db";
import bcrypt from "bcryptjs";

export const dynamic = "force-dynamic";

export async function POST(req: NextRequest) {
  try {
    const { token, password } = await req.json();

    if (!token || !password || password.trim().length < 6) {
      return NextResponse.json({ error: "Dữ liệu hoặc mật khẩu mới quá ngắn (tối thiểu 6 ký tự)." }, { status: 400 });
    }

    const now = new Date();

    // 1. Tìm người dùng có token hợp lệ và chưa hết hạn
    const user = await db.user.findFirst({
      where: {
        resetToken: token,
        resetTokenExpires: {
          gt: now,
        },
      },
    });

    if (!user) {
      return NextResponse.json({ error: "Mã khôi phục không hợp lệ hoặc đã hết hạn sử dụng." }, { status: 400 });
    }

    // 2. Hash mật khẩu mới bằng bcryptjs
    const passwordHash = await bcrypt.hash(password.trim(), 10);

    // 3. Cập nhật mật khẩu và xóa token reset trong database
    await db.user.update({
      where: { id: user.id },
      data: {
        passwordHash: passwordHash,
        resetToken: null,
        resetTokenExpires: null,
      },
    });

    return NextResponse.json({
      message: "Đặt lại mật khẩu thành công! Vui lòng đăng nhập lại.",
    }, { status: 200 });

  } catch (error: any) {
    console.error("Reset Password Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống đặt lại mật khẩu." }, { status: 500 });
  }
}
