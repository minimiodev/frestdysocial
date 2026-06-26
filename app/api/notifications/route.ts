import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser } from "@/lib/auth";
import { db } from "@/lib/db";

export const dynamic = "force-dynamic";

/**
 * GET: Lấy danh sách thông báo của user
 */
export async function GET(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Chưa đăng nhập." }, { status: 401 });
    }

    const notifications = await db.notification.findMany({
      where: {
        userId: user.id,
      },
      include: {
        sender: {
          select: {
            id: true,
            username: true,
            fullName: true,
            avatarFilename: true,
            verificationType: true,
          },
        },
      },
      orderBy: {
        createdAt: "desc",
      },
    });

    return NextResponse.json({ notifications });
  } catch (error: any) {
    console.error("Get Notifications Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}

/**
 * PUT: Đánh dấu tất cả thông báo của user là đã đọc
 */
export async function PUT(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Chưa đăng nhập." }, { status: 401 });
    }

    const body = await req.json().catch(() => ({}));
    const { id } = body;

    if (id) {
      // Đánh dấu 1 thông báo cụ thể là đã đọc
      const updated = await db.notification.update({
        where: {
          id: parseInt(id),
          userId: user.id,
        },
        data: {
          isRead: true,
        },
      });
      return NextResponse.json({ message: "Đã đánh dấu thông báo là đã đọc.", updated });
    } else {
      // Đánh dấu tất cả thông báo của user là đã đọc
      const result = await db.notification.updateMany({
        where: {
          userId: user.id,
          isRead: false,
        },
        data: {
          isRead: true,
        },
      });
      return NextResponse.json({ message: "Đã đánh dấu tất cả thông báo là đã đọc.", count: result.count });
    }
  } catch (error: any) {
    console.error("Update Notifications Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}

/**
 * DELETE: Xóa thông báo
 */
export async function DELETE(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Chưa đăng nhập." }, { status: 401 });
    }

    const url = new URL(req.url);
    const id = url.searchParams.get("id");

    if (!id) {
      // Xóa tất cả thông báo
      await db.notification.deleteMany({
        where: {
          userId: user.id,
        },
      });
      return NextResponse.json({ message: "Đã xóa toàn bộ thông báo." });
    } else {
      // Xóa 1 thông báo cụ thể
      await db.notification.delete({
        where: {
          id: parseInt(id),
          userId: user.id,
        },
      });
      return NextResponse.json({ message: "Đã xóa thông báo thành công." });
    }
  } catch (error: any) {
    console.error("Delete Notifications Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}
