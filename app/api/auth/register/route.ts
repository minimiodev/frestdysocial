import { NextRequest, NextResponse } from "next/server";
import { db } from "@/lib/db";
import { hashPassword, signToken, setSessionCookie } from "@/lib/auth";

export async function POST(req: NextRequest) {
  try {
    const { username, email, password, fullName } = await req.json();

    if (!username || !email || !password) {
      return NextResponse.json(
        { error: "Vui lòng nhập đầy đủ thông tin bắt buộc (username, email, password)." },
        { status: 400 }
      );
    }

    const cleanUsername = username.trim().toLowerCase();
    const cleanEmail = email.trim().toLowerCase();

    // Kiểm tra trùng username
    const existingUserByUsername = await db.user.findUnique({
      where: { username: cleanUsername },
    });
    if (existingUserByUsername) {
      return NextResponse.json(
        { error: "Tên đăng nhập đã được sử dụng." },
        { status: 400 }
      );
    }

    // Kiểm tra trùng email
    const existingUserByEmail = await db.user.findUnique({
      where: { email: cleanEmail },
    });
    if (existingUserByEmail) {
      return NextResponse.json(
        { error: "Email này đã được đăng ký." },
        { status: 400 }
      );
    }

    // Tạo mật khẩu băm
    const hashedPassword = hashPassword(password);

    // Lưu người dùng mới vào cơ sở dữ liệu
    const newUser = await db.user.create({
      data: {
        username: cleanUsername,
        email: cleanEmail,
        passwordHash: hashedPassword,
        fullName: fullName ? fullName.trim() : cleanUsername,
        avatarFilename: "avatar_default.png",
        bio: "",
        status: "active",
      },
    });

    // Tạo JWT token
    const token = signToken({
      userId: newUser.id,
      username: newUser.username,
    });

    // Gửi session cookie trong response
    const response = NextResponse.json(
      { message: "Đăng ký tài khoản thành công!", user: { id: newUser.id, username: newUser.username, email: newUser.email } },
      { status: 201 }
    );
    setSessionCookie(response, token);

    return response;
  } catch (error: any) {
    console.error("Register Error:", error);
    return NextResponse.json(
      { error: "Có lỗi xảy ra trong quá trình đăng ký. Vui lòng thử lại." },
      { status: 500 }
    );
  }
}
