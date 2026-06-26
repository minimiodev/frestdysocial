import { NextRequest, NextResponse } from "next/server";
import { clearSessionCookie } from "@/lib/auth";

export async function POST(req: NextRequest) {
  const response = NextResponse.json({ message: "Đăng xuất thành công!" });
  clearSessionCookie(response);
  // Xóa cả cookie identity nếu có
  response.cookies.delete("frest_identity");
  return response;
}
