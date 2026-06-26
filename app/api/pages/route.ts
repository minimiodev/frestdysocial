import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser } from "@/lib/auth";
import { db } from "@/lib/db";

export const dynamic = "force-dynamic";

/**
 * GET: Lấy danh sách các trang do user quản lý
 */
export async function GET(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Chưa đăng nhập." }, { status: 401 });
    }

    const pages = await db.page.findMany({
      where: { ownerId: user.id },
      orderBy: { createdAt: "desc" },
    });

    return NextResponse.json({ pages });
  } catch (error: any) {
    console.error("Get Pages Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}

/**
 * POST: Tạo trang Fanpage mới
 */
export async function POST(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Chưa đăng nhập." }, { status: 401 });
    }

    const { pageName, pageUsername, category, bio, avatarFilename } = await req.json();

    if (!pageName || !pageName.trim() || !pageUsername || !pageUsername.trim()) {
      return NextResponse.json({ error: "Tên trang và tên người dùng trang (Username) không được để trống." }, { status: 400 });
    }

    const cleanUsername = pageUsername.trim().toLowerCase().replace(/[^a-z0-9_.]/g, "");
    if (cleanUsername.length < 3) {
      return NextResponse.json({ error: "Username trang không hợp lệ hoặc quá ngắn." }, { status: 400 });
    }

    // 1. Kiểm tra xem username trang đã bị trùng trong bảng Page hoặc User chưa
    const existingPage = await db.page.findUnique({
      where: { pageUsername: cleanUsername },
    });

    if (existingPage) {
      return NextResponse.json({ error: "Tên người dùng trang (Username) đã tồn tại. Vui lòng chọn tên khác." }, { status: 400 });
    }

    const existingUser = await db.user.findUnique({
      where: { username: cleanUsername },
    });

    if (existingUser) {
      return NextResponse.json({ error: "Tên người dùng trang trùng với tài khoản cá nhân đã có trong hệ thống." }, { status: 400 });
    }

    // 2. Tạo trang mới
    const newPage = await db.page.create({
      data: {
        ownerId: user.id,
        pageName: pageName.trim(),
        pageUsername: cleanUsername,
        category: category || "Cộng đồng",
        bio: bio || "",
        avatarFilename: avatarFilename || "avatar_default.png",
      },
    });

    return NextResponse.json({
      message: "Tạo trang mới thành công!",
      page: newPage,
    }, { status: 201 });

  } catch (error: any) {
    console.error("Create Page Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống khi tạo trang." }, { status: 500 });
  }
}
