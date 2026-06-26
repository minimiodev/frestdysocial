import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser } from "@/lib/auth";
import { db } from "@/lib/db";

/**
 * GET: Lấy toàn bộ stories đang hoạt động (trong vòng 24h) của user và những người user đang follow
 */
export async function GET(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Chưa đăng nhập" }, { status: 401 });
    }

    const time24hAgo = new Date(Date.now() - 24 * 60 * 60 * 1000);

    // Lấy danh sách following
    const followings = await db.follow.findMany({
      where: { followerId: user.id },
      select: { followedId: true },
    });
    const followedIds = followings.map((f) => f.followedId);
    
    // Gồm cả chính mình
    const activeUserIds = [user.id, ...followedIds];

    // Query stories của những user này trong vòng 24h
    const stories = await db.story.findMany({
      where: {
        userId: { in: activeUserIds },
        createdAt: { gte: time24hAgo },
      },
      include: {
        user: {
          select: {
            id: true,
            username: true,
            fullName: true,
            avatarFilename: true,
          }
        },
        views: {
          select: {
            viewerId: true,
          }
        },
        reactions: {
          select: {
            userId: true,
            reactionType: true,
          }
        }
      },
      orderBy: { createdAt: "desc" },
    });

    // Group stories theo từng user để frontend hiển thị dạng danh sách tròn giống Instagram/Frest cũ
    const groupedStoriesMap = new Map<number, any>();
    for (const story of stories) {
      if (!groupedStoriesMap.has(story.userId)) {
        groupedStoriesMap.set(story.userId, {
          user: story.user,
          stories: [],
        });
      }
      groupedStoriesMap.get(story.userId).stories.push({
        id: story.id,
        mediaFilename: story.mediaFilename,
        mediaType: story.mediaType,
        caption: story.caption,
        createdAt: story.createdAt,
        views: story.views,
        reactions: story.reactions,
      });
    }

    return NextResponse.json({
      groupedStories: Array.from(groupedStoriesMap.values()),
    });
  } catch (error) {
    console.error("Get Stories error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}

/**
 * POST: Tạo story mới
 */
export async function POST(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Chưa đăng nhập" }, { status: 401 });
    }

    const { mediaFilename, mediaType, caption } = await req.json();

    if (!mediaFilename || !mediaType) {
      return NextResponse.json({ error: "Thiếu thông tin file story." }, { status: 400 });
    }

    const newStory = await db.story.create({
      data: {
        userId: user.id,
        mediaFilename: mediaFilename,
        mediaType: mediaType, // "image" or "video"
        caption: caption || null,
      },
    });

    return NextResponse.json({ message: "Đăng tin thành công!", story: newStory }, { status: 201 });
  } catch (error) {
    console.error("Create Story error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}
