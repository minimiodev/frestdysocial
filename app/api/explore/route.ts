import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser } from "@/lib/auth";
import { db } from "@/lib/db";

export const dynamic = "force-dynamic";

export async function GET(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);

    // 1. Lấy danh sách hashtag xu hướng (sắp xếp theo số lượng bài viết giảm dần)
    const trendingHashtags = await db.hashtag.findMany({
      include: {
        _count: {
          select: { posts: true },
        },
      },
      orderBy: {
        posts: {
          _count: "desc",
        },
      },
      take: 8,
    });

    // Định dạng lại kết quả hashtag
    const hashtags = trendingHashtags.map((h) => ({
      id: h.id,
      tag: h.tag,
      postCount: h._count.posts,
    }));

    // 2. Gợi ý người dùng để follow (những người mình chưa follow)
    let followedUserIds: number[] = [];
    if (user) {
      const followings = await db.follow.findMany({
        where: { followerId: user.id },
        select: { followedId: true },
      });
      followedUserIds = followings.map((f) => f.followedId);
      followedUserIds.push(user.id); // Không tự gợi ý bản thân
    }

    const suggestedUsers = await db.user.findMany({
      where: {
        id: {
          notIn: followedUserIds,
        },
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
      orderBy: {
        createdAt: "desc",
      },
      take: 5,
    });

    // 3. Lấy wiki moods tâm trạng của mọi người còn hạn dùng (hoặc mood mới nhất)
    const now = new Date();
    const wikiMoods = await db.wikiMood.findMany({
      where: {
        OR: [
          { expiresAt: null },
          { expiresAt: { gt: now } },
        ],
      },
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
      orderBy: {
        createdAt: "desc",
      },
      take: 10,
    });

    return NextResponse.json({
      hashtags,
      suggestedUsers,
      wikiMoods,
    });
  } catch (error: any) {
    console.error("Explore API Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}
