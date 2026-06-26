import { NextRequest, NextResponse } from "next/server";
import { db } from "@/lib/db";
import { comparePassword, signToken, setSessionCookie } from "@/lib/auth";

export async function POST(req: NextRequest) {
  try {
    const { usernameOrEmail, password } = await req.json();

    if (!usernameOrEmail || !password) {
      return NextResponse.json(
        { error: "Vui lòng nhập tên tài khoản/email và mật khẩu." },
        { status: 400 }
      );
    }

    const cleanInput = usernameOrEmail.trim().toLowerCase();

    // Tìm user theo username hoặc email hoặc số điện thoại
    const user = await db.user.findFirst({
      where: {
        OR: [
          { username: cleanInput },
          { email: cleanInput },
          { phoneNumber: cleanInput },
        ],
      },
    });

    if (!user) {
      return NextResponse.json(
        { error: "Tài khoản hoặc mật khẩu không chính xác." },
        { status: 400 }
      );
    }

    // Kiểm tra trạng thái tài khoản
    if (user.status === "banned") {
      return NextResponse.json(
        { error: `Tài khoản đã bị khóa. Lý do: ${user.statusReason || "Vi phạm điều khoản"}` },
        { status: 403 }
      );
    }

    if (user.status === "suspended") {
      return NextResponse.json(
        { error: "Tài khoản đang bị tạm ngưng." },
        { status: 403 }
      );
    }

    // Xác thực mật khẩu
    const isPasswordMatch = comparePassword(password, user.passwordHash);
    if (!isPasswordMatch) {
      return NextResponse.json(
        { error: "Tài khoản hoặc mật khẩu không chính xác." },
        { status: 400 }
      );
    }

    // Cập nhật hoạt động cuối cùng của người dùng
    await db.user.update({
      where: { id: user.id },
      data: { lastActive: new Date() },
    });

    // Tạo JWT token
    const token = signToken({
      userId: user.id,
      username: user.username,
    });

    // Gửi session cookie trong response
    const response = NextResponse.json({
      message: "Đăng nhập thành công!",
      user: {
        id: user.id,
        username: user.username,
        fullName: user.fullName,
        email: user.email,
        avatarFilename: user.avatarFilename,
      },
    });
    setSessionCookie(response, token);

    return response;
  } catch (error: any) {
    console.error("Login Error:", error);
    return NextResponse.json(
      { error: "Có lỗi xảy ra trong quá trình đăng nhập. Vui lòng thử lại." },
      { status: 500 }
    );
  }
}
