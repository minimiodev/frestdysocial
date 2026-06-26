import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser } from "@/lib/auth";
import { db } from "@/lib/db";

export const dynamic = "force-dynamic";

export async function GET(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Bạn cần đăng nhập để xem dấu trang." }, { status: 401 });
    }

    // Lấy danh sách các bookmark của user hiện tại
    const bookmarks = await db.bookmark.findMany({
      where: {
        userId: user.id,
      },
      include: {
        post: {
          include: {
            user: {
              select: {
                id: true,
                username: true,
                fullName: true,
                avatarFilename: true,
                verificationType: true,
              },
            },
            page: {
              select: {
                id: true,
                pageName: true,
                pageUsername: true,
                avatarFilename: true,
                isVerified: true,
              },
            },
            repostOf: {
              include: {
                user: {
                  select: {
                    id: true,
                    username: true,
                    fullName: true,
                    avatarFilename: true,
                  },
                },
              },
            },
            reactions: {
              select: {
                userId: true,
                reactionType: true,
              },
            },
            bookmarks: {
              select: {
                userId: true,
              },
            },
            replies: {
              take: 3,
              orderBy: { createdAt: "desc" },
              include: {
                user: {
                  select: {
                    id: true,
                    username: true,
                    fullName: true,
                    avatarFilename: true,
                  },
                },
              },
            },
            polls: {
              include: {
                options: {
                  include: {
                    votes: {
                      select: {
                        userId: true,
                      },
                    },
                  },
                },
              },
            },
            _count: {
              select: {
                replies: true,
                reactions: true,
                reposts: true,
              },
            },
          },
        },
      },
      orderBy: {
        createdAt: "desc",
      },
    });

    // Lọc ra các bài viết thực tế (nếu bài viết gốc bị xóa thì post có thể là null)
    const posts = bookmarks
      .map((b) => b.post)
      .filter((p) => p !== null && !p.isCopyrightViolation);

    return NextResponse.json({ posts });
  } catch (error: any) {
    console.error("Get Bookmarks Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}
