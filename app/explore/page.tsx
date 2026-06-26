"use client";

import { useState, useEffect } from "react";
import useSWR from "swr";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { 
  Compass, 
  TrendingUp, 
  UserPlus, 
  Smile, 
  Search, 
  UserCheck, 
  ArrowRight 
} from "lucide-react";

const fetcher = (url: string) => fetch(url).then((res) => res.json());

export default function ExplorePage() {
  const router = useRouter();
  const [currentUser, setCurrentUser] = useState<any>(null);
  const [searchVal, setSearchVal] = useState("");
  const [followingStates, setFollowingStates] = useState<Record<number, boolean>>({});

  // 1. Fetch user
  useEffect(() => {
    fetch("/api/auth/me")
      .then((res) => {
        if (res.ok) return res.json();
        throw new Error();
      })
      .then((data) => {
        setCurrentUser(data.user);
      })
      .catch(() => {});
  }, []);

  // 2. Fetch explore data
  const { data, mutate, error } = useSWR("/api/explore", fetcher);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchVal.trim()) {
      router.push(`/search?q=${encodeURIComponent(searchVal.trim())}`);
    }
  };

  // 3. Xử lý follow nhanh
  const handleFollowToggle = async (userId: number, username: string) => {
    if (!currentUser) {
      router.push("/login");
      return;
    }

    // Optimistic UI update
    setFollowingStates((prev) => ({
      ...prev,
      [userId]: !prev[userId],
    }));

    try {
      const res = await fetch(`/api/users/${username}/follow`, {
        method: "POST",
      });
      if (res.ok) {
        mutate(); // Revalidate explore data
      } else {
        // Hoàn tác nếu lỗi
        setFollowingStates((prev) => ({
          ...prev,
          [userId]: !prev[userId],
        }));
      }
    } catch (e) {
      setFollowingStates((prev) => ({
        ...prev,
        [userId]: !prev[userId],
      }));
    }
  };

  return (
    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
      {/* Cột chính: Khám phá & Gợi ý */}
      <div className="lg:col-span-2 space-y-6">
        
        {/* Search bar on Mobile/Tablet */}
        <form onSubmit={handleSearchSubmit} className="relative w-full block sm:hidden">
          <span className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
            <Search className="h-4.5 w-4.5 text-gray-400" />
          </span>
          <input
            type="text"
            placeholder="Tìm kiếm bài viết, hashtag, bạn bè..."
            value={searchVal}
            onChange={(e) => setSearchVal(e.target.value)}
            className="w-full pl-10 pr-4 py-3 text-xs bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl focus:outline-none focus:bg-white dark:focus:bg-[#18181c] focus:border-primary/30 transition-all font-medium shadow-sm"
          />
        </form>

        {/* Explore Card Header */}
        <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl p-5 shadow-premium flex items-center gap-4 relative overflow-hidden">
          <div className="absolute -right-6 -bottom-6 w-24 h-24 bg-accent-purple/5 rounded-full blur-2xl" />
          <div className="w-12 h-12 rounded-xl bg-accent-purple/10 flex items-center justify-center text-accent-purple shrink-0 shadow-sm">
            <Compass className="w-6 h-6" />
          </div>
          <div>
            <h2 className="font-extrabold text-lg">Khám phá Frest</h2>
            <p className="text-xs text-gray-400 font-medium">Bắt kịp các xu hướng mới nhất và kết nối cùng cộng đồng</p>
          </div>
        </div>

        {/* WikiMoods section */}
        <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl p-5 shadow-premium space-y-4">
          <h3 className="font-extrabold text-sm flex items-center gap-2">
            <Smile className="w-4.5 h-4.5 text-accent-orange" />
            Tâm trạng của mọi người hôm nay
          </h3>
          
          {data?.wikiMoods ? (
            data.wikiMoods.length > 0 ? (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                {data.wikiMoods.map((mood: any) => (
                  <div
                    key={mood.id}
                    className="p-3 rounded-2xl border flex items-center gap-3 shadow-sm hover:shadow-md transition-all bg-gray-50/40 dark:bg-[#151518]"
                    style={{ borderColor: mood.color ? `${mood.color}25` : "var(--card-border)" }}
                  >
                    {/* Avatar */}
                    <Link href={`/profile/${mood.user.username}`}>
                      <img
                        src={`/uploads/avatars/${mood.user.avatarFilename}`}
                        alt={mood.user.fullName}
                        className="w-10 h-10 rounded-xl object-cover shrink-0 ring-2"
                        style={{ ringColor: mood.color || "rgba(0,0,0,0.1)" }}
                      />
                    </Link>
                    <div className="overflow-hidden flex-1">
                      <div className="flex items-center gap-1.5">
                        <Link href={`/profile/${mood.user.username}`} className="font-bold text-xs hover:underline truncate">
                          {mood.user.fullName}
                        </Link>
                        <span className="text-[14px]">{mood.emoji}</span>
                      </div>
                      <p className="text-[10.5px] text-gray-500 dark:text-gray-400 font-semibold truncate mt-0.5">
                        {mood.content}
                      </p>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-center text-xs text-gray-400 font-semibold py-4">Mọi người chưa chia sẻ tâm trạng nào hôm nay.</p>
            )
          ) : error ? (
            <p className="text-center text-xs text-gray-400 font-semibold py-4">Không thể tải dữ liệu tâm trạng.</p>
          ) : (
            // Shimmer moods
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              {[1, 2].map((n) => (
                <div key={n} className="p-3 bg-gray-100 dark:bg-[#1e1e24] border border-[var(--card-border)] rounded-2xl flex items-center gap-3">
                  <div className="w-10 h-10 rounded-xl animate-shimmer shrink-0" />
                  <div className="space-y-1.5 flex-1">
                    <div className="h-3.5 w-24 rounded animate-shimmer" />
                    <div className="h-2.5 w-16 rounded animate-shimmer" />
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Gợi ý theo dõi (Who to follow) */}
        <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl p-5 shadow-premium space-y-4">
          <h3 className="font-extrabold text-sm flex items-center gap-2">
            <UserPlus className="w-4.5 h-4.5 text-accent-green" />
            Đề xuất kết nối cho bạn
          </h3>

          <div className="space-y-3.5">
            {data?.suggestedUsers ? (
              data.suggestedUsers.length > 0 ? (
                data.suggestedUsers.map((sUser: any) => {
                  const isFollowing = followingStates[sUser.id] ?? false;
                  return (
                    <div key={sUser.id} className="flex items-center justify-between gap-4 p-3 rounded-2xl border border-[var(--card-border)] hover:bg-gray-50/50 dark:hover:bg-[#18181c] transition-all">
                      <div className="flex items-center gap-3 overflow-hidden">
                        <Link href={`/profile/${sUser.username}`}>
                          <img
                            src={`/uploads/avatars/${sUser.avatarFilename}`}
                            alt={sUser.fullName}
                            className="w-11 h-11 rounded-xl object-cover shrink-0"
                            onError={(e) => {
                              e.currentTarget.src = "/assets/images/icons/icon-192x192.png";
                            }}
                          />
                        </Link>
                        <div className="overflow-hidden">
                          <Link href={`/profile/${sUser.username}`} className="font-extrabold text-xs hover:underline block truncate text-gray-800 dark:text-gray-200">
                            {sUser.fullName}
                          </Link>
                          <span className="text-[10px] text-gray-400 font-medium block">@{sUser.username}</span>
                          {sUser.bio && (
                            <p className="text-[10px] text-gray-500 font-semibold truncate mt-0.5 max-w-[200px] sm:max-w-sm">
                              {sUser.bio}
                            </p>
                          )}
                        </div>
                      </div>

                      {/* Follow Button */}
                      <button
                        onClick={() => handleFollowToggle(sUser.id, sUser.username)}
                        className={`px-4 py-2 text-[10px] font-extrabold rounded-xl transition-all shadow-sm flex items-center gap-1 shrink-0 ${
                          isFollowing
                            ? "bg-gray-100 hover:bg-gray-200 dark:bg-[#202024] dark:hover:bg-[#2a2a30] text-primary"
                            : "bg-primary hover:bg-primary-hover text-white"
                        }`}
                      >
                        {isFollowing ? (
                          <>
                            <UserCheck className="w-3.5 h-3.5" />
                            <span>Đã theo dõi</span>
                          </>
                        ) : (
                          <>
                            <UserPlus className="w-3.5 h-3.5" />
                            <span>Theo dõi</span>
                          </>
                        )}
                      </button>
                    </div>
                  );
                })
              ) : (
                <p className="text-center text-xs text-gray-400 font-semibold py-4">Hệ thống hiện không có đề xuất nào mới.</p>
              )
            ) : error ? (
              <p className="text-center text-xs text-gray-400 font-semibold py-4">Không thể tải danh sách đề xuất.</p>
            ) : (
              // Shimmer suggested users
              <div className="space-y-3">
                {[1, 2].map((n) => (
                  <div key={n} className="p-3 bg-gray-100 dark:bg-[#1e1e24] border border-[var(--card-border)] rounded-2xl flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <div className="w-11 h-11 rounded-xl animate-shimmer shrink-0" />
                      <div className="space-y-1.5">
                        <div className="h-3 w-28 rounded animate-shimmer" />
                        <div className="h-2 w-16 rounded animate-shimmer" />
                      </div>
                    </div>
                    <div className="w-16 h-8 rounded-xl animate-shimmer" />
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

      </div>

      {/* Cột phụ: Hashtag xu hướng (Chỉ hiện trên desktop) */}
      <div className="space-y-6 hidden lg:block">
        <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl p-5 shadow-premium space-y-4">
          <h3 className="font-extrabold text-sm flex items-center gap-2">
            <TrendingUp className="w-4.5 h-4.5 text-primary" />
            Hashtag xu hướng
          </h3>

          <div className="space-y-2">
            {data?.hashtags ? (
              data.hashtags.length > 0 ? (
                data.hashtags.map((tag: any) => (
                  <Link
                    key={tag.id}
                    href={`/search?q=${tag.tag}`}
                    className="flex justify-between items-center p-3 hover:bg-gray-50 dark:hover:bg-[#202024] rounded-2xl transition-all border border-transparent hover:border-[var(--card-border)] text-xs font-bold text-gray-600 dark:text-gray-400"
                  >
                    <span>#{tag.tag}</span>
                    <span className="bg-gray-100 dark:bg-[#28282f] text-[9.5px] px-2 py-0.5 rounded-full font-extrabold text-gray-500">
                      {tag.postCount} bài viết
                    </span>
                  </Link>
                ))
              ) : (
                <p className="text-center text-xs text-gray-400 font-semibold py-4">Chưa có hashtag xu hướng.</p>
              )
            ) : error ? (
              <p className="text-center text-xs text-gray-400 font-semibold py-4">Không thể tải danh sách hashtag.</p>
            ) : (
              // Shimmer hashtags
              <div className="space-y-2">
                {[1, 2, 3].map((n) => (
                  <div key={n} className="h-10 w-full rounded-2xl animate-shimmer" />
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
