import { cookies } from "next/headers";
import { notFound } from "next/navigation";
import { verifyToken } from "@/lib/auth";
import { db } from "@/lib/db";
import PostCard from "@/components/PostCard";
import AvatarImage from "@/components/AvatarImage";
import Link from "next/link";
import PageClient from "./PageClient";

interface PageDetailPageProps {
  params: {
    pageUsername: string;
  };
}

export default async function PageDetailPage({ params }: PageDetailPageProps) {
  const pageUsername = params.pageUsername.toLowerCase();

  // 1. Tìm thông tin trang Fanpage
  const page = await db.page.findUnique({
    where: { pageUsername },
    include: {
      owner: {
        select: {
          id: true,
          username: true,
          fullName: true,
        }
      },
      _count: {
        select: {
          posts: true,
          followers: true,
        },
      },
    },
  });

  if (!page) {
    notFound();
  }

  // 2. Lấy thông tin user hiện tại đang đăng nhập
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

  // 3. Kiểm tra xem user hiện tại đã follow trang này chưa
  let isFollowing = false;
  if (currentUser) {
    const followRecord = await db.pageFollow.findUnique({
      where: {
        userId_pageId: {
          userId: currentUser.id,
          pageId: page.id,
        },
      },
    });
    isFollowing = !!followRecord;
  }

  // 4. Lấy danh sách các bài viết của Trang
  const posts = await db.post.findMany({
    where: {
      pageId: page.id,
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

  const isOwnPage = currentUser?.id === page.ownerId;

  return (
    <div className="space-y-6">
      {/* Page Header Card */}
      <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-3xl overflow-hidden shadow-premium">
        {/* Cover Photo Mock */}
        <div className="h-44 bg-gradient-to-r from-primary/20 via-accent-green/10 to-accent-orange/20 relative" />

        {/* Page Meta info */}
        <div className="px-6 pb-6 relative flex flex-col md:flex-row items-center md:items-end justify-between -mt-10 gap-4">
          <div className="flex flex-col md:flex-row items-center md:items-end gap-4 text-center md:text-left">
            <AvatarImage
              src={page.avatarFilename.startsWith("http") ? page.avatarFilename : `/uploads/avatars/${page.avatarFilename}`}
              alt={page.pageName}
              className="w-28 h-28 rounded-2xl object-cover border-4 border-[var(--card-bg)] shadow-md z-10"
            />
            <div className="mb-2">
              <div className="flex items-center justify-center md:justify-start gap-1.5">
                <h2 className="text-xl font-extrabold">{page.pageName}</h2>
                <span className="bg-accent-green/10 text-accent-green text-[9px] font-extrabold px-2 py-0.5 rounded-full uppercase tracking-wider">
                  Trang
                </span>
                {page.isVerified && (
                  <svg className="w-5 h-5 inline-block shrink-0" viewBox="0 0 24 24" title="Trang chính thức">
                    <g fillRule="evenodd" transform="translate(-92)">
                      <path fill="#1877f2" d="m115.887 14.475-1.269-2.475 1.267-2.474a1.02 1.02 0 0 0-.355-1.326l-2.334-1.51-.14-2.775a1.018 1.018 0 0 0-.97-.971l-2.778-.14-1.51-2.336a1.02 1.02 0 0 0-1.324-.354L104 1.38 101.526.114a1.02 1.02 0 0 0-1.326.354l-1.509 2.336-2.777.14a1.017 1.017 0 0 0-.97.97l-.14 2.777L92.468 8.2a1.02 1.02 0 0 0-.354 1.325L93.382 12l-1.268 2.474a1.02 1.02 0 0 0 .355 1.326l2.335 1.509.14 2.776c.025.528.443.945.97.971l2.777.14 1.51 2.336a1.02 1.02 0 0 0 1.324.354L104 22.62l2.474 1.267c.469.242 1.039.09 1.326-.355l1.51-2.335 2.776-.14c.527-.026.945-.443.97-.97l.14-2.777 2.336-1.51c.443-.286.595-.856.354-1.324" />
                      <path fill="#ffffff" d="m109.207 9.707-6.5 6.5a.996.996 0 0 1-1.414 0l-3-3a1 1 0 1 1 1.414-1.414L102 14.086l5.793-5.793a1 1 0 1 1 1.414 1.414" />
                    </g>
                  </svg>
                )}
              </div>
              <p className="text-xs text-gray-400 font-medium">@{page.pageUsername} • {page.category}</p>
              {page.bio && <p className="text-xs text-gray-500 font-medium mt-1.5 max-w-md">{page.bio}</p>}
            </div>
          </div>

          {/* Page Follow / Management Button */}
          <div className="mb-2">
            {currentUser ? (
              <PageClient isFollowing={isFollowing} pageUsername={page.pageUsername} isOwnPage={isOwnPage} />
            ) : (
              <Link
                href="/login"
                className="px-5 py-2 bg-primary hover:bg-primary-hover text-white rounded-xl text-xs font-bold shadow-premium"
              >
                Đăng nhập để theo dõi trang
              </Link>
            )}
          </div>
        </div>

        {/* Stats & Owners bar */}
        <div className="border-t border-[var(--card-border)] px-6 py-4 flex flex-col sm:flex-row justify-between gap-3 text-xs font-semibold text-gray-500">
          <div className="flex gap-6 justify-center sm:justify-start">
            <div>
              <span className="text-gray-800 dark:text-gray-200 font-extrabold">{page._count.posts}</span> bài viết
            </div>
            <div>
              <span className="text-gray-800 dark:text-gray-200 font-extrabold">{page._count.followers}</span> người theo dõi trang
            </div>
          </div>
          <div className="text-center sm:text-right text-[10px]">
            Quản trị bởi:{" "}
            <Link href={`/profile/${page.owner.username}`} className="text-primary font-bold hover:underline">
              @{page.owner.username} ({page.owner.fullName})
            </Link>
          </div>
        </div>
      </div>

      {/* Posts feed */}
      <div className="max-w-2xl mx-auto space-y-4">
        <h3 className="font-extrabold text-sm text-gray-700 dark:text-gray-300 px-1">Bài viết của trang</h3>
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
          <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl p-12 text-center text-gray-400 font-medium text-xs">
            Trang này chưa đăng bài viết nào.
          </div>
        )}
      </div>
    </div>
  );
}
