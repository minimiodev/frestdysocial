"use client";

import { useState, useEffect } from "react";
import { useSearchParams, useRouter } from "next/navigation";
import useSWR from "swr";
import PostCard from "@/components/PostCard";
import Link from "next/link";
import { 
  Search, 
  UserCheck, 
  UserPlus, 
  MessageSquare, 
  FileText,
  Users
} from "lucide-react";

const fetcher = (url: string) => fetch(url).then((res) => res.json());

export default function SearchPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const query = searchParams.get("q") || "";

  const [currentUser, setCurrentUser] = useState<any>(null);
  const [searchVal, setSearchVal] = useState(query);
  const [activeTab, setActiveTab] = useState<"posts" | "users">("posts");
  const [followingStates, setFollowingStates] = useState<Record<number, boolean>>({});

  // 1. Fetch user data
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

  // 2. Fetch search results
  const { data, mutate, error, isValidating } = useSWR(
    query ? `/api/search?q=${encodeURIComponent(query)}` : null,
    fetcher
  );

  useEffect(() => {
    setSearchVal(query);
  }, [query]);

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

    setFollowingStates((prev) => ({
      ...prev,
      [userId]: !prev[userId],
    }));

    try {
      const res = await fetch(`/api/users/${username}/follow`, {
        method: "POST",
      });
      if (res.ok) {
        mutate();
      } else {
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
    <div className="max-w-2xl mx-auto space-y-6">
      {/* Search Header Form */}
      <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl p-5 shadow-premium space-y-4">
        <div>
          <h2 className="font-extrabold text-lg flex items-center gap-2">
            <Search className="w-5 h-5 text-primary" />
            Tìm kiếm trên Frest
          </h2>
          <p className="text-xs text-gray-400 font-medium mt-0.5">Tìm kiếm bài viết, hashtag hoặc bạn bè của bạn</p>
        </div>

        <form onSubmit={handleSearchSubmit} className="relative w-full">
          <span className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
            <Search className="h-4.5 w-4.5 text-gray-400" />
          </span>
          <input
            type="text"
            placeholder="Nhập nội dung cần tìm..."
            value={searchVal}
            onChange={(e) => setSearchVal(e.target.value)}
            className="w-full pl-10 pr-4 py-3 text-xs bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-2xl focus:outline-none focus:bg-white dark:focus:bg-[#1c1c20] focus:border-primary transition-all font-medium"
          />
        </form>
      </div>

      {query && (
        <>
          {/* Tab navigation */}
          <div className="flex border-b border-[var(--card-border)] gap-6 text-xs font-bold text-gray-400">
            <button
              onClick={() => setActiveTab("posts")}
              className={`pb-2.5 px-1 border-b-2 transition-all flex items-center gap-2 ${
                activeTab === "posts"
                  ? "border-primary text-primary"
                  : "border-transparent hover:text-gray-600 dark:hover:text-gray-200"
              }`}
            >
              <FileText className="w-4 h-4" />
              Bài đăng
              {data?.posts && (
                <span className="bg-gray-100 dark:bg-[#202024] px-1.5 py-0.5 rounded-full text-[9px] font-extrabold">
                  {data.posts.length}
                </span>
              )}
            </button>
            <button
              onClick={() => setActiveTab("users")}
              className={`pb-2.5 px-1 border-b-2 transition-all flex items-center gap-2 ${
                activeTab === "users"
                  ? "border-primary text-primary"
                  : "border-transparent hover:text-gray-600 dark:hover:text-gray-200"
              }`}
            >
              <Users className="w-4 h-4" />
              Mọi người
              {data?.users && (
                <span className="bg-gray-100 dark:bg-[#202024] px-1.5 py-0.5 rounded-full text-[9px] font-extrabold">
                  {data.users.length}
                </span>
              )}
            </button>
          </div>

          {/* Results display */}
          <div className="space-y-4">
            {isValidating && !data ? (
              // Loading shimmer
              <div className="space-y-4">
                {[1, 2].map((n) => (
                  <div key={n} className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl p-4 space-y-4">
                    <div className="flex gap-3">
                      <div className="w-11 h-11 rounded-xl animate-shimmer" />
                      <div className="space-y-1.5 flex-1">
                        <div className="h-3 w-28 rounded animate-shimmer" />
                        <div className="h-2 w-16 rounded animate-shimmer" />
                      </div>
                    </div>
                    <div className="h-16 w-full rounded-xl animate-shimmer" />
                  </div>
                ))}
              </div>
            ) : activeTab === "posts" ? (
              data?.posts && data.posts.length > 0 ? (
                data.posts.map((post: any) => (
                  <PostCard
                    key={post.id}
                    post={post}
                    currentUserId={currentUser?.id}
                    isAdult={currentUser?.isAdult || false}
                    onRefresh={mutate}
                  />
                ))
              ) : (
                <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl p-12 text-center text-gray-400 font-medium text-xs space-y-2">
                  <FileText className="w-8 h-8 mx-auto opacity-30 text-gray-400" />
                  <p className="font-bold text-gray-600 dark:text-gray-300">Không tìm thấy bài viết nào</p>
                  <p className="text-[10px] text-gray-400">Hãy thử tìm kiếm với các từ khóa khác.</p>
                </div>
              )
            ) : (
              data?.users && data.users.length > 0 ? (
                <div className="space-y-3">
                  {data.users.map((sUser: any) => {
                    const isFollowing = followingStates[sUser.id] ?? false;
                    return (
                      <div key={sUser.id} className="flex items-center justify-between gap-4 p-3.5 bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl hover:shadow-sm transition-all">
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
                        {currentUser && currentUser.id !== sUser.id && (
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
                        )}
                      </div>
                    );
                  })}
                </div>
              ) : (
                <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl p-12 text-center text-gray-400 font-medium text-xs space-y-2">
                  <Users className="w-8 h-8 mx-auto opacity-30 text-gray-400" />
                  <p className="font-bold text-gray-600 dark:text-gray-300">Không tìm thấy người dùng nào</p>
                  <p className="text-[10px] text-gray-400">Hãy thử tìm kiếm với các từ khóa khác.</p>
                </div>
              )
            )}
          </div>
        </>
      )}
    </div>
  );
}
