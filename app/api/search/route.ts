import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser } from "@/lib/auth";
import { db } from "@/lib/db";

export const dynamic = "force-dynamic";

export async function GET(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);
    const url = new URL(req.url);
    const query = url.searchParams.get("q") || "";

    if (!query.trim()) {
      return NextResponse.json({ posts: [], users: [] });
    }

    const q = query.trim().toLowerCase();

    // 1. Tìm kiếm User
    const users = await db.user.findMany({
      where: {
        OR: [
          { username: { contains: q, mode: "insensitive" } },
          { fullName: { contains: q, mode: "insensitive" } },
        ],
        status: "active",
      },
      select: {
        id: true,
        username: true,
        fullName: true,
        avatarFilename: true,
        bio: true,
        verificationType: true,
      },
      take: 20,
    });

    // 2. Tìm kiếm Bài viết (Post)
    const posts = await db.post.findMany({
      where: {
        OR: [
          { content: { contains: q, mode: "insensitive" } },
          {
            hashtags: {
              some: {
                hashtag: {
                  tag: { contains: q.replace("#", ""), mode: "insensitive" },
                },
              },
            },
          },
        ],
        isCopyrightViolation: false,
      },
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
      orderBy: {
        createdAt: "desc",
      },
      take: 30,
    });

    return NextResponse.json({ posts, users });
  } catch (error: any) {
    console.error("Search API Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}
