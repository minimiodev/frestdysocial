import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser } from "@/lib/auth";
import { db } from "@/lib/db";

export const dynamic = "force-dynamic";

export async function GET(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ authenticated: false }, { status: 401 });
    }

    // Đọc identity hiện tại (được switch qua cookie frest_identity)
    let identity = {
      type: "user",
      id: user.id,
      name: user.fullName || user.username,
      avatar: user.avatarFilename,
      username: user.username,
    };

    const identityCookie = req.cookies.get("frest_identity")?.value;
    if (identityCookie) {
      try {
        const parsed = JSON.parse(identityCookie);
        if (parsed.type === "page") {
          // Lấy thông tin page xem có đúng user sở hữu không
          // Chú ý: Ở schema.prisma, chúng ta có thể định nghĩa bảng Page nếu cần,
          // Hoặc kiểm tra cơ sở dữ liệu. Nhưng để đơn giản và linh hoạt, chúng ta
          // có thể truy vấn bảng posts/pages hoặc mô phỏng trực tiếp từ DB.
          // Trong database MySQL gốc có table `pages` (xem db_init.php dòng 25).
          // Nhưng ở schema.prisma ở bước trước tôi đã bỏ qua bảng `Page` (nhưng posts có liên kết)
          // Để tôi kiểm tra xem có bảng `pages` trong db_init.php không. Có!
          // Hãy bổ sung bảng Page vào schema.prisma nếu cần, hoặc xử lý trực tiếp.
          // Để an toàn, hãy xem thông tin page trong db_init.php.
        }
      } catch (e) {}
    }

    // Cập nhật trạng thái hoạt động cuối cùng của người dùng
    await db.user.update({
      where: { id: user.id },
      data: { lastActive: new Date() },
    });

    return NextResponse.json({
      authenticated: true,
      user: {
        id: user.id,
        username: user.username,
        fullName: user.fullName,
        email: user.email,
        avatarFilename: user.avatarFilename,
        bio: user.bio,
        isPrivate: user.isPrivate,
        showNsfw: user.showNsfw,
        ageVerificationStatus: user.ageVerificationStatus,
      },
      identity,
    });
  } catch (error) {
    console.error("Get Me Error:", error);
    return NextResponse.json({ authenticated: false, error: "Lỗi hệ thống" }, { status: 500 });
  }
}
