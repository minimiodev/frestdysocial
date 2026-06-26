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
      verificationType: user.verificationType,
    };

    const identityCookie = req.cookies.get("frest_identity")?.value;
    if (identityCookie) {
      try {
        const parsed = JSON.parse(decodeURIComponent(identityCookie));
        if (parsed.type === "page" && parsed.id) {
          const page = await db.page.findFirst({
            where: {
              id: parseInt(parsed.id),
              ownerId: user.id,
            },
          });
          if (page) {
            identity = {
              type: "page",
              id: page.id,
              name: page.pageName,
              avatar: page.avatarFilename,
              username: page.pageUsername,
              verificationType: page.isVerified ? "official" : null,
            };
          }
        }
      } catch (e) {
        console.error("Lỗi phân tích active identity:", e);
      }
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
        verificationType: user.verificationType,
      },
      identity,
    });
  } catch (error) {
    console.error("Get Me Error:", error);
    return NextResponse.json({ authenticated: false, error: "Lỗi hệ thống" }, { status: 500 });
  }
}
