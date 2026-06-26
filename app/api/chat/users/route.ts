import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser } from "@/lib/auth";
import { db } from "@/lib/db";

export const dynamic = "force-dynamic";

export async function GET(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Chưa đăng nhập." }, { status: 401 });
    }

    // Lấy toàn bộ người dùng khác trong hệ thống để hiển thị trong danh sách có thể chat
    const users = await db.user.findMany({
      where: {
        id: {
          not: user.id,
        },
        status: "active",
      },
      select: {
        id: true,
        username: true,
        fullName: true,
        avatarFilename: true,
        bio: true,
      },
      orderBy: {
        createdAt: "desc",
      },
      take: 50,
    });

    const formattedUsers = users.map((u) => ({
      id: u.id,
      type: "user",
      name: u.fullName || u.username,
      username: u.username,
      avatar: u.avatarFilename,
      lastMessage: u.bio || "Bắt đầu cuộc trò chuyện mới",
    }));

    return NextResponse.json({ users: formattedUsers });
  } catch (error: any) {
    console.error("Get Chat Users Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}
