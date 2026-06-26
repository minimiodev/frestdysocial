import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser } from "@/lib/auth";
import { db } from "@/lib/db";

export const dynamic = "force-dynamic";

// Items per page
const ITEMS_PER_PAGE = 15;

/**
 * GET: Lấy danh sách bài viết (Feed chính)
 * Hỗ trợ phân trang bằng cursor hoặc page
 */
export async function GET(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);
    // Vẫn cho phép người dùng chưa đăng nhập xem các bài viết công khai nếu cần, 
    // Nhưng nếu đã đăng nhập thì sẽ tối ưu hóa lấy feed theo danh sách follow.
    
    const url = new URL(req.url);
    const page = parseInt(url.searchParams.get("page") || "1");
    const skip = (page - 1) * ITEMS_PER_PAGE;
    const userIdParam = url.searchParams.get("userId"); // Filter theo profile user cụ thể

    let whereClause: any = {
      isCopyrightViolation: false,
    };

    // Nếu lọc theo user cụ thể
    if (userIdParam) {
      whereClause.userId = parseInt(userIdParam);
    } else if (user) {
      // Feed chính của user đã đăng nhập: hiển thị bài viết của chính mình và những người mình đang follow
      const followings = await db.follow.findMany({
        where: { followerId: user.id },
        select: { followedId: true },
      });
      const followedIds = followings.map((f) => f.followedId);
      
      whereClause.OR = [
        { userId: user.id },
        { userId: { in: followedIds } }
      ];
    }

    // Nếu không đăng nhập hoặc feed công cộng, lấy tất cả các bài viết không vi phạm bản quyền
    const posts = await db.post.findMany({
      where: whereClause,
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
          }
        },
        repostOf: {
          include: {
            user: {
              select: {
                id: true,
                username: true,
                fullName: true,
                avatarFilename: true,
              }
            }
          }
        },
        reactions: {
          select: {
            userId: true,
            reactionType: true,
          }
        },
        replies: {
          take: 3, // Lấy sẵn 3 bình luận mới nhất
          orderBy: { createdAt: "desc" },
          include: {
            user: {
              select: {
                id: true,
                username: true,
                fullName: true,
                avatarFilename: true,
                verificationType: true,
              }
            }
          }
        },
        polls: {
          include: {
            options: {
              include: {
                votes: {
                  select: {
                    userId: true,
                  }
                }
              }
            }
          }
        },
        _count: {
          select: {
            replies: true,
            reactions: true,
            reposts: true,
          }
        }
      },
      orderBy: [
        { isPinned: "desc" }, // Ghim bài viết lên đầu
        { createdAt: "desc" }
      ],
      skip: skip,
      take: ITEMS_PER_PAGE,
    });

    return NextResponse.json({ posts, page, hasMore: posts.length === ITEMS_PER_PAGE });
  } catch (error: any) {
    console.error("Get Feed Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}

/**
 * POST: Tạo bài viết mới
 */
export async function POST(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Bạn cần đăng nhập để đăng bài viết." }, { status: 401 });
    }

    const body = await req.json();
    const { 
      content, 
      imageFilename, 
      videoFilename, 
      audioFilename, 
      documentFilename, 
      softwareFilename,
      isNsfw,
      repostOfPostId,
      pageId,
      linkPreview,
      pollOptions, // Mảng các text options để tạo poll ví dụ: ["Lựa chọn 1", "Lựa chọn 2"]
      pollQuestion
    } = body;

    if (!content && !imageFilename && !videoFilename && !audioFilename && !repostOfPostId) {
      return NextResponse.json({ error: "Nội dung bài đăng không được để trống." }, { status: 400 });
    }

    // Token ngẫu nhiên cho bài viết (thay thế token ở PHP)
    const postToken = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);

    // Bắt đầu transaction để tạo Post và các Hashtag, Poll liên quan
    const newPost = await db.$transaction(async (tx) => {
      // 1. Tạo Post
      const post = await tx.post.create({
        data: {
          userId: user.id,
          content: content || "",
          imageFilename: imageFilename || null,
          videoFilename: videoFilename || null,
          audioFilename: audioFilename || null,
          documentFilename: documentFilename || null,
          softwareFilename: softwareFilename || null,
          isNsfw: !!isNsfw,
          repostOfPostId: repostOfPostId ? parseInt(repostOfPostId) : null,
          pageId: pageId ? parseInt(pageId) : null,
          postToken: postToken,
          linkPreviewUrl: linkPreview?.url || null,
          linkPreviewTitle: linkPreview?.title || null,
          linkPreviewDesc: linkPreview?.desc || null,
          linkPreviewImage: linkPreview?.image || null,
        },
      });

      // 2. Tạo Poll nếu có tùy chọn
      if (pollQuestion && pollOptions && Array.isArray(pollOptions) && pollOptions.length >= 2) {
        const poll = await tx.poll.create({
          data: {
            postId: post.id,
            question: pollQuestion,
          }
        });

        for (const optText of pollOptions) {
          if (optText.trim()) {
            await tx.pollOption.create({
              data: {
                pollId: poll.id,
                optionText: optText.trim(),
              }
            });
          }
        }
      }

      // 3. Trích xuất và lưu Hashtag (ví dụ: #frest #nextjs)
      const hashtags = (content || "").match(/#[a-zA-Z0-9_]+/g);
      if (hashtags) {
        for (const tagRaw of hashtags) {
          const tag = tagRaw.replace("#", "").toLowerCase().trim();
          if (tag) {
            // Tìm hoặc tạo hashtag
            let hashtagRecord = await tx.hashtag.findUnique({
              where: { tag },
            });
            if (!hashtagRecord) {
              hashtagRecord = await tx.hashtag.create({
                data: { tag },
              });
            }

            // Tạo post-hashtag relation
            await tx.postHashtag.create({
              data: {
                postId: post.id,
                hashtagId: hashtagRecord.id,
              }
            });
          }
        }
      }

      // 4. Tạo thông báo (Notification) nếu đây là một Repost bài viết
      if (repostOfPostId) {
        const originalPost = await tx.post.findUnique({
          where: { id: parseInt(repostOfPostId) },
        });
        if (originalPost && originalPost.userId !== user.id) {
          await tx.notification.create({
            data: {
              userId: originalPost.userId,
              senderId: user.id,
              type: "repost",
              targetId: post.id,
            }
          });
        }
      }

      return post;
    });

    return NextResponse.json({ message: "Đăng bài viết thành công!", post: newPost }, { status: 201 });
  } catch (error: any) {
    console.error("Create Post Error:", error);
    return NextResponse.json({ error: "Lỗi tạo bài viết" }, { status: 500 });
  }
}
