import jwt from "jsonwebtoken";
import bcrypt from "bcryptjs";
import { NextRequest, NextResponse } from "next/server";
import { db } from "./db";

const JWT_SECRET = process.env.JWT_SECRET || "fallback-secret-for-frest-app-jwt-token";
const COOKIE_NAME = "frest_session";

interface JWTPayload {
  userId: number;
  username: string;
}

/**
 * Hash mật khẩu bằng bcryptjs
 */
export function hashPassword(password: string): string {
  const salt = bcrypt.genSaltSync(10);
  return bcrypt.hashSync(password, salt);
}

/**
 * Kiểm tra mật khẩu có khớp không
 */
export function comparePassword(password: string, hash: string): boolean {
  return bcrypt.compareSync(password, hash);
}

/**
 * Ký JWT token
 */
export function signToken(payload: JWTPayload): string {
  return jwt.sign(payload, JWT_SECRET, { expiresIn: "30d" });
}

/**
 * Giải mã và verify JWT token
 */
export function verifyToken(token: string): JWTPayload | null {
  try {
    return jwt.verify(token, JWT_SECRET) as JWTPayload;
  } catch (error) {
    return null;
  }
}

/**
 * Lấy User hiện tại đang đăng nhập dựa trên JWT cookie từ request
 */
export async function getAuthenticatedUser(req: NextRequest) {
  const token = req.cookies.get(COOKIE_NAME)?.value;
  if (!token) return null;

  const decoded = verifyToken(token);
  if (!decoded) return null;

  const user = await db.user.findUnique({
    where: { id: decoded.userId },
  });

  if (!user || user.status !== "active") return null;

  return user;
}

/**
 * Lấy Admin hiện tại đang đăng nhập
 */
export async function getAuthenticatedAdmin(req: NextRequest) {
  const token = req.cookies.get("frest_admin_session")?.value;
  if (!token) return null;

  try {
    const decoded = jwt.verify(token, JWT_SECRET) as { adminId: number; username: string };
    const admin = await db.admin.findUnique({
      where: { id: decoded.adminId },
    });
    return admin;
  } catch (error) {
    return null;
  }
}

/**
 * Cấu hình HTTP-only Cookie khi đăng nhập thành công
 */
export function setSessionCookie(response: NextResponse, token: string) {
  response.cookies.set(COOKIE_NAME, token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    maxAge: 30 * 24 * 60 * 60, // 30 ngày (tương đương GC max lifetime của PHP session cũ)
    path: "/",
  });
}

/**
 * Xóa HTTP-only Cookie khi đăng xuất
 */
export function clearSessionCookie(response: NextResponse) {
  response.cookies.set(COOKIE_NAME, "", {
    httpOnly: true,
    expires: new Date(0),
    path: "/",
  });
}
