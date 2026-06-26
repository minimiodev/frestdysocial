import { NextRequest, NextResponse } from "next/server";
import { db } from "@/lib/db";
import bcrypt from "bcryptjs";
import jwt from "jsonwebtoken";

const JWT_SECRET = process.env.JWT_SECRET || "fallback-secret-for-frest-app-jwt-token";

export async function POST(req: NextRequest) {
  try {
    const { username, password } = await req.json();

    if (!username || !password) {
      return NextResponse.json({ error: "Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu." }, { status: 400 });
    }

    // Tìm admin trong DB
    const admin = await db.admin.findUnique({
      where: { username: username.trim() },
    });

    if (!admin) {
      return NextResponse.json({ error: "Tên đăng nhập hoặc mật khẩu quản trị không đúng." }, { status: 401 });
    }

    // Verify password
    const isValid = bcrypt.compareSync(password, admin.passwordHash);
    if (!isValid) {
      return NextResponse.json({ error: "Tên đăng nhập hoặc mật khẩu quản trị không đúng." }, { status: 401 });
    }

    // Phát hành token JWT chứa adminId
    const adminToken = jwt.sign(
      { adminId: admin.id, username: admin.username },
      JWT_SECRET,
      { expiresIn: "7d" }
    );

    const response = NextResponse.json({
      success: true,
      message: "Đăng nhập quản trị viên thành công!",
      admin: {
        id: admin.id,
        username: admin.username,
      }
    }, { status: 200 });

    // Set cookie frest_admin_session
    response.cookies.set("frest_admin_session", adminToken, {
      httpOnly: true,
      secure: process.env.NODE_ENV === "production",
      sameSite: "lax",
      maxAge: 7 * 24 * 60 * 60, // 7 ngày
      path: "/",
    });

    return response;

  } catch (error) {
    console.error("Admin Login Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống khi đăng nhập admin." }, { status: 500 });
  }
}
