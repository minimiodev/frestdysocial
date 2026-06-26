import { cookies } from "next/headers";
import { notFound, redirect } from "next/navigation";
import { verifyToken } from "@/lib/auth";
import { db } from "@/lib/db";
import PostCard from "@/components/PostCard";
import AvatarImage from "@/components/AvatarImage";
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
            <AvatarImage
              src={`/uploads/avatars/${profileUser.avatarFilename}`}
              alt={profileUser.fullName}
              className="w-28 h-28 rounded-2xl object-cover border-4 border-[var(--card-bg)] shadow-md z-10"
            />
            <div className="mb-2">
              <div className="flex items-center justify-center md:justify-start gap-1.5">
                <h2 className="text-xl font-extrabold">{profileUser.fullName}</h2>
                {profileUser.verificationType === "official" && (
                  <svg className="w-5 h-5 inline-block shrink-0" viewBox="0 0 24 24" title="Đã xác minh">
                    <g fillRule="evenodd" transform="translate(-92)">
                      <path fill="#1877f2" d="m115.887 14.475-1.269-2.475 1.267-2.474a1.02 1.02 0 0 0-.355-1.326l-2.334-1.51-.14-2.775a1.018 1.018 0 0 0-.97-.971l-2.778-.14-1.51-2.336a1.02 1.02 0 0 0-1.324-.354L104 1.38 101.526.114a1.02 1.02 0 0 0-1.326.354l-1.509 2.336-2.777.14a1.017 1.017 0 0 0-.97.97l-.14 2.777L92.468 8.2a1.02 1.02 0 0 0-.354 1.325L93.382 12l-1.268 2.474a1.02 1.02 0 0 0 .355 1.326l2.335 1.509.14 2.776c.025.528.443.945.97.971l2.777.14 1.51 2.336a1.02 1.02 0 0 0 1.324.354L104 22.62l2.474 1.267c.469.242 1.039.09 1.326-.355l1.51-2.335 2.776-.14c.527-.026.945-.443.97-.97l.14-2.777 2.336-1.51c.443-.286.595-.856.354-1.324" />
                      <path fill="#ffffff" d="m109.207 9.707-6.5 6.5a.996.996 0 0 1-1.414 0l-3-3a1 1 0 1 1 1.414-1.414L102 14.086l5.793-5.793a1 1 0 1 1 1.414 1.414" />
                    </g>
                  </svg>
                )}
                {profileUser.verificationType === "subscribed" && (
                  <svg className="w-5 h-5 text-amber-500 fill-current inline-block shrink-0" viewBox="0 0 24 24" title="Frest Subscribed">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H7c0-2.76 2.24-5 5-5s5 2.24 5 5c0 1.04-.42 1.99-1.07 2.75z" />
                  </svg>
                )}
                {profileUser.verificationType === "developer" && (
                  <span className="text-[10px] bg-purple-500/10 text-purple-500 border border-purple-500/20 px-2 py-0.5 rounded-full font-bold flex items-center gap-0.5" title="Nhà phát triển">
                    ⚙ Dev
                  </span>
                )}
                {profileUser.verificationType === "business" && (
                  <span className="text-[10px] bg-green-500/10 text-green-500 border border-green-500/20 px-2 py-0.5 rounded-full font-bold flex items-center gap-0.5" title="Doanh nghiệp">
                    💼 Biz
                  </span>
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
