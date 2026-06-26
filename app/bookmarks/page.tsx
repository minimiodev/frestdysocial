"use client";

import { useState, useEffect } from "react";
import useSWR from "swr";
import PostCard from "@/components/PostCard";
import { Bookmark, Sparkles } from "lucide-react";

const fetcher = (url: string) => fetch(url).then((res) => res.json());

export default function BookmarksPage() {
  const [currentUser, setCurrentUser] = useState<any>(null);

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
      .catch(() => {
        window.location.href = "/login";
      });
  }, []);

  // 2. Fetch bookmarks
  const { data, mutate, error } = useSWR(
    currentUser ? "/api/bookmarks" : null,
    fetcher
  );

  if (!currentUser) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <div className="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      {/* Header */}
      <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl p-5 shadow-premium flex items-center gap-4 relative overflow-hidden">
        <div className="absolute -right-6 -bottom-6 w-24 h-24 bg-primary/5 rounded-full blur-2xl" />
        <div className="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary shrink-0 shadow-sm">
          <Bookmark className="w-6 h-6 fill-primary" />
        </div>
        <div>
          <h2 className="font-extrabold text-lg">Bài viết đã lưu</h2>
          <p className="text-xs text-gray-400 font-medium">Lưu trữ các bài viết hay để xem lại bất cứ lúc nào</p>
        </div>
      </div>

      {/* Bookmarks List */}
      <div className="space-y-4">
        {data?.posts ? (
          data.posts.length > 0 ? (
            data.posts.map((post: any) => (
              <PostCard
                key={post.id}
                post={post}
                currentUserId={currentUser.id}
                isAdult={currentUser.isAdult}
                onRefresh={mutate}
              />
            ))
          ) : (
            <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl p-12 text-center text-gray-400 font-medium text-xs space-y-2">
              <Bookmark className="w-8 h-8 mx-auto opacity-30 text-gray-400" />
              <p className="font-bold text-gray-600 dark:text-gray-300">Chưa lưu bài viết nào</p>
              <p className="text-[10px] text-gray-400">Các bài đăng bạn lưu từ bảng tin sẽ xuất hiện ở đây.</p>
            </div>
          )
        ) : error ? (
          <p className="text-center text-xs text-gray-400 py-6">Không thể kết nối cơ sở dữ liệu.</p>
        ) : (
          // Shimmer loading
          <div className="space-y-4">
            {[1, 2].map((n) => (
              <div key={n} className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl p-4 space-y-4">
                <div className="flex gap-3">
                  <div className="w-11 h-11 rounded-xl animate-shimmer" />
                  <div className="space-y-1.5 flex-1 py-1">
                    <div className="h-3 w-28 rounded animate-shimmer" />
                    <div className="h-2 w-16 rounded animate-shimmer" />
                  </div>
                </div>
                <div className="h-16 w-full rounded-xl animate-shimmer" />
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
