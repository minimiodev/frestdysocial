import { cookies } from "next/headers";
import { notFound, redirect } from "next/navigation";
import { verifyToken } from "@/lib/auth";
import { db } from "@/lib/db";
import PostCard from "@/components/PostCard";
import Link from "next/link";
import ProfileClient from "./ProfileClient"; // Tạo component client-side phụ trợ để xử lý settings & state tabs

interface ProfilePageProps {
  params: {
    username: string;
  };
}

export default async function ProfilePage({ params }: ProfilePageProps) {
  const username = params.username.toLowerCase();

  // 1. Tìm thông tin user dựa vào username
  const profileUser = await db.user.findUnique({
    where: { username },
    include: {
      _count: {
        select: {
          posts: true,
          followers: true,
          following: true,
        },
      },
    },
  });

  if (!profileUser) {
    notFound();
  }

  // 2. Lấy user hiện tại đang xem trang (đăng nhập)
  let currentUser = null;
  const cookieStore = cookies();
  const token = cookieStore.get("frest_session")?.value;

  if (token) {
    const decoded = verifyToken(token);
    if (decoded) {
      currentUser = await db.user.findUnique({
        where: { id: decoded.userId },
      });
    }
  }

  // 3. Kiểm tra xem user hiện tại đã follow profile này chưa
  let isFollowing = false;
  if (currentUser && currentUser.id !== profileUser.id) {
    const followRecord = await db.follow.findUnique({
      where: {
        followerId_followedId: {
          followerId: currentUser.id,
          followedId: profileUser.id,
        },
      },
    });
    isFollowing = !!followRecord;
  }

  // 4. Lấy danh sách bài viết của user
  const posts = await db.post.findMany({
    where: {
      userId: profileUser.id,
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
        }
      },
      reactions: { select: { userId: true, reactionType: true } },
      bookmarks: { select: { userId: true } },
      repostOf: {
        include: {
          user: {
            select: { id: true, username: true, fullName: true, avatarFilename: true }
          }
        }
      },
      polls: {
        include: {
          options: {
            include: {
              votes: true
            }
          }
        }
      },
      _count: {
        select: { replies: true, reactions: true, reposts: true }
      }
    },
    orderBy: { createdAt: "desc" },
  });

  const isOwnProfile = currentUser?.id === profileUser.id;

  return (
    <div className="space-y-6">
      {/* Profile Header Card */}
      <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-3xl overflow-hidden shadow-premium">
        {/* Cover Photo Mock */}
        <div className="h-44 bg-gradient-to-r from-primary/30 via-accent-purple/20 to-accent-pink/30 relative" />

        {/* User Meta info */}
        <div className="px-6 pb-6 relative flex flex-col md:flex-row items-center md:items-end justify-between -mt-10 gap-4">
          <div className="flex flex-col md:flex-row items-center md:items-end gap-4 text-center md:text-left">
            <img
              src={`/uploads/avatars/${profileUser.avatarFilename}`}
              alt={profileUser.fullName}
              className="w-28 h-28 rounded-2xl object-cover border-4 border-[var(--card-bg)] shadow-md z-10"
              onError={(e) => {
                e.currentTarget.src = "/assets/images/icons/icon-192x192.png";
              }}
            />
            <div className="mb-2">
              <div className="flex items-center justify-center md:justify-start gap-1.5">
                <h2 className="text-xl font-extrabold">{profileUser.fullName}</h2>
                {profileUser.verificationType && (
                  <span className="text-primary font-extrabold text-sm">✓</span>
                )}
              </div>
              <p className="text-xs text-gray-400 font-medium">@{profileUser.username}</p>
              {profileUser.bio && <p className="text-xs text-gray-500 font-medium mt-1.5 max-w-md">{profileUser.bio}</p>}
            </div>
          </div>

          {/* Follow / Edit Button */}
          <div className="mb-2">
            {isOwnProfile ? (
              <div className="flex gap-2">
                <span className="text-[10px] bg-primary/10 text-primary font-bold uppercase px-3 py-1.5 rounded-xl border border-primary/20">
                  Tài khoản của bạn
                </span>
              </div>
            ) : currentUser ? (
              <ProfileClient isFollowing={isFollowing} targetUsername={profileUser.username} isSetting={false} />
            ) : (
              <Link
                href="/login"
                className="px-5 py-2 bg-primary hover:bg-primary-hover text-white rounded-xl text-xs font-bold shadow-premium"
              >
                Đăng nhập để theo dõi
              </Link>
            )}
          </div>
        </div>

        {/* Stats bar */}
        <div className="border-t border-[var(--card-border)] px-6 py-4 flex gap-6 text-xs font-semibold text-gray-500 justify-center md:justify-start">
          <div>
            <span className="text-gray-800 dark:text-gray-200 font-extrabold">{profileUser._count.posts}</span> bài viết
          </div>
          <div>
            <span className="text-gray-800 dark:text-gray-200 font-extrabold">{profileUser._count.followers}</span> người theo dõi
          </div>
          <div>
            <span className="text-gray-800 dark:text-gray-200 font-extrabold">{profileUser._count.following}</span> đang theo dõi
          </div>
        </div>
      </div>

      {/* Profile Tabs Content (Articles / Settings) */}
      <ProfileClient 
        isOwnProfile={isOwnProfile} 
        targetUsername={profileUser.username} 
        isSetting={true} 
        userObj={JSON.parse(JSON.stringify(profileUser))}
      >
        <div className="space-y-4">
          {posts.length > 0 ? (
            posts.map((post) => (
              <PostCard
                key={post.id}
                post={post}
                currentUserId={currentUser?.id}
                isAdult={currentUser?.isAdult || false}
              />
            ))
          ) : (
            <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl p-8 text-center text-gray-400 font-medium text-xs">
              Chưa có bài viết nào được đăng trên trang cá nhân.
            </div>
          )}
        </div>
      </ProfileClient>
    </div>
  );
}
