import { NextRequest, NextResponse } from "next/server";

export async function POST(req: NextRequest) {
  const response = NextResponse.json({
    success: true,
    message: "Đăng xuất tài khoản quản trị thành công!",
  });
  
  response.cookies.set("frest_admin_session", "", {
    httpOnly: true,
    expires: new Date(0),
    path: "/",
  });
  
  return response;
}
