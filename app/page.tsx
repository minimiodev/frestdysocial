"use client";

import { useState, useEffect } from "react";
import useSWR from "swr";
import StoryTray from "@/components/StoryTray";
import PostCard from "@/components/PostCard";
import { Sparkles, Image, Video, Music, Calendar, Smile, Send, Compass } from "lucide-react";
import Link from "next/link";

const fetcher = (url: string) => fetch(url).then((res) => res.json());

export default function FeedPage() {
  const [currentUser, setCurrentUser] = useState<any>(null);
  const [currentIdentity, setCurrentIdentity] = useState<any>(null);
  
  // Khung soạn thảo bài viết mới
  const [content, setContent] = useState("");
  const [imageFile, setImageFile] = useState("");
  const [videoFile, setVideoFile] = useState("");
  const [isNsfw, setIsNsfw] = useState(false);
  const [showPoll, setShowPoll] = useState(false);
  const [pollQuestion, setPollQuestion] = useState("");
  const [pollOptions, setPollOptions] = useState(["", ""]);
  const [posting, setPosting] = useState(false);

  // Fetch me
  useEffect(() => {
    fetch("/api/auth/me")
      .then((res) => {
        if (res.ok) return res.json();
        throw new Error();
      })
      .then((data) => {
        setCurrentUser(data.user);
        setCurrentIdentity(data.identity);
      })
      .catch(() => {
        // Redirect to login if not authenticated
        window.location.href = "/login";
      });
  }, []);

  // Fetch stories
  const { data: storiesData, mutate: mutateStories } = useSWR(
    currentUser ? "/api/stories" : null,
    fetcher
  );

  // Fetch posts
  const { data: postsData, mutate: mutatePosts, error: postsError } = useSWR(
    currentUser ? "/api/posts" : null,
    fetcher
  );

  const handlePostSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!content.trim() && !imageFile && !videoFile) return;

    setPosting(true);
    try {
      const res = await fetch("/api/posts", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          content,
          imageFilename: imageFile || null,
          videoFilename: videoFile || null,
          isNsfw,
          pageId: currentIdentity?.type === "page" ? currentIdentity.id : null,
          pollQuestion: showPoll ? pollQuestion : null,
          pollOptions: showPoll ? pollOptions : null,
        }),
      });

      if (res.ok) {
        setContent("");
        setImageFile("");
        setVideoFile("");
        setIsNsfw(false);
        setShowPoll(false);
        setPollQuestion("");
        setPollOptions(["", ""]);
        mutatePosts(); // Cập nhật lại danh sách bài viết ngay lập tức
      }
    } catch (err) {
      console.error(err);
    } finally {
      setPosting(false);
    }
  };

  const handleAddPollOption = () => {
    setPollOptions([...pollOptions, ""]);
  };

  const handlePollOptionChange = (index: number, val: string) => {
    const updated = [...pollOptions];
    updated[index] = val;
    setPollOptions(updated);
  };

  if (!currentUser) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <div className="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
      {/* Main Feed Column */}
      <div className="lg:col-span-2 space-y-6">
        {/* Story Tray */}
        <StoryTray
          groupedStories={storiesData?.groupedStories || []}
          currentUserId={currentUser.id}
          onRefresh={mutateStories}
        />

        {/* Create Post Card */}
        <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl p-4 shadow-premium">
          <form onSubmit={handlePostSubmit} className="space-y-4">
            <div className="flex gap-3">
              <img
                src={`/uploads/avatars/${currentIdentity?.avatar}`}
                alt="Avatar"
                className="w-10 h-10 rounded-xl object-cover"
                onError={(e) => {
                  e.currentTarget.src = "/assets/images/icons/icon-192x192.png";
                }}
              />
              <textarea
                value={content}
                onChange={(e) => setContent(e.target.value)}
                placeholder={`${currentIdentity?.name} ơi, hôm nay bạn đang nghĩ gì thế?`}
                rows={3}
                className="flex-1 bg-transparent border-none text-sm placeholder-gray-400 focus:ring-0 resize-none focus:outline-none"
              />
            </div>

            {/* Poll Inputs */}
            {showPoll && (
              <div className="p-4 bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-2xl space-y-3">
                <input
                  type="text"
                  placeholder="Đặt câu hỏi bình chọn..."
                  value={pollQuestion}
                  onChange={(e) => setPollQuestion(e.target.value)}
                  className="w-full bg-transparent border-b border-[var(--card-border)] pb-2 text-sm focus:outline-none focus:border-primary font-bold"
                />
                <div className="space-y-2">
                  {pollOptions.map((opt, idx) => (
                    <input
                      key={idx}
                      type="text"
                      placeholder={`Lựa chọn ${idx + 1}`}
                      value={opt}
                      onChange={(e) => handlePollOptionChange(idx, e.target.value)}
                      className="w-full bg-white dark:bg-[#202024] px-3 py-2 rounded-xl text-xs border border-[var(--card-border)] focus:outline-none focus:border-primary"
                    />
                  ))}
                </div>
                <button
                  type="button"
                  onClick={handleAddPollOption}
                  className="text-xs font-bold text-primary hover:underline"
                >
                  + Thêm tùy chọn
                </button>
              </div>
            )}

            {/* Optional attachments inputs */}
            {(imageFile || videoFile) && (
              <div className="p-3 bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-2xl space-y-2 text-xs">
                {imageFile && (
                  <div className="flex justify-between items-center">
                    <span className="truncate max-w-[280px]">🖼️ Ảnh: {imageFile}</span>
                    <button type="button" className="text-accent-pink font-bold" onClick={() => setImageFile("")}>Xóa</button>
                  </div>
                )}
                {videoFile && (
                  <div className="flex justify-between items-center">
                    <span className="truncate max-w-[280px]">🎥 Video: {videoFile}</span>
                    <button type="button" className="text-accent-pink font-bold" onClick={() => setVideoFile("")}>Xóa</button>
                  </div>
                )}
              </div>
            )}

            {/* Actions Bar */}
            <div className="flex items-center justify-between border-t border-[var(--card-border)] pt-3">
              <div className="flex gap-2.5 text-gray-500">
                <button
                  type="button"
                  onClick={() => {
                    const url = prompt("Nhập đường dẫn ảnh từ R2:");
                    if (url) setImageFile(url);
                  }}
                  className="p-2 hover:bg-gray-100 dark:hover:bg-[#202024] rounded-xl hover:text-primary transition-colors"
                  title="Thêm ảnh"
                >
                  <Image className="w-5 h-5" />
                </button>
                <button
                  type="button"
                  onClick={() => {
                    const url = prompt("Nhập đường dẫn video từ R2:");
                    if (url) setVideoFile(url);
                  }}
                  className="p-2 hover:bg-gray-100 dark:hover:bg-[#202024] rounded-xl hover:text-primary transition-colors"
                  title="Thêm video"
                >
                  <Video className="w-5 h-5" />
                </button>
                <button
                  type="button"
                  onClick={() => setShowPoll(!showPoll)}
                  className="p-2 hover:bg-gray-100 dark:hover:bg-[#202024] rounded-xl hover:text-primary transition-colors"
                  title="Tạo cuộc thăm dò"
                >
                  <Calendar className="w-5 h-5" />
                </button>
                <button
                  type="button"
                  onClick={() => setIsNsfw(!isNsfw)}
                  className={`px-3 py-1.5 rounded-xl text-xs font-bold border transition-colors ${
                    isNsfw
                      ? "bg-accent-pink/10 text-accent-pink border-accent-pink/30"
                      : "border-transparent hover:bg-gray-100 dark:hover:bg-[#202024]"
                  }`}
                  title="Đánh dấu nội dung 18+"
                >
                  NSFW
                </button>
              </div>

              <button
                type="submit"
                disabled={posting}
                className="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-hover text-white text-xs font-bold shadow-premium flex items-center gap-1.5 transition-all"
              >
                <span>{posting ? "Đang đăng..." : "Đăng bài"}</span>
                <Send className="w-3.5 h-3.5" />
              </button>
            </div>
          </form>
        </div>

        {/* Feed Posts list */}
        <div className="space-y-4">
          {postsData?.posts && postsData.posts.length > 0 ? (
            postsData.posts.map((post: any) => (
              <PostCard
                key={post.id}
                post={post}
                currentUserId={currentUser.id}
                isAdult={currentUser.isAdult}
                onRefresh={mutatePosts}
              />
            ))
          ) : postsError ? (
            <p className="text-center text-xs text-gray-400 py-6">Không thể kết nối database.</p>
          ) : (
            // Shimmer loading
            <div className="space-y-4">
              {[1, 2, 3].map((n) => (
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

      {/* Right widgets column (Suggestions, active friends) */}
      <div className="space-y-6 hidden lg:block">
        {/* Verification banner if not verified */}
        {currentUser.ageVerificationStatus !== "verified" && (
          <div className="p-4 bg-gradient-to-tr from-accent-pink/5 via-accent-purple/5 to-primary/5 border border-primary/20 rounded-2xl text-center space-y-3">
            <h4 className="font-bold text-xs uppercase tracking-wider text-primary flex items-center justify-center gap-1">
              <Sparkles className="w-4 h-4" />
              Xác minh tuổi 18+
            </h4>
            <p className="text-[11px] text-gray-500 dark:text-gray-400 leading-normal">
              Xác thực độ tuổi của bạn bằng cách tải ảnh CCCD/Hộ chiếu để mở khóa quyền xem các nội dung NSFW nhạy cảm.
            </p>
            <Link
              href={`/profile/${currentUser.username}?tab=verify`}
              className="inline-block px-4 py-2 bg-primary hover:bg-primary-hover text-white text-xs font-bold rounded-xl shadow-premium transition-all"
            >
              Xác thực ngay
            </Link>
          </div>
        )}

        {/* Explore Card widget */}
        <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl p-4 shadow-premium space-y-3">
          <h4 className="font-extrabold text-sm flex items-center gap-2">
            <Compass className="w-4.5 h-4.5 text-primary" />
            Khám phá xu hướng
          </h4>
          <div className="space-y-2 text-xs">
            {["frest", "nextjs", "supabase", "cloudflare_r2", "antigravity"].map((tag) => (
              <Link
                key={tag}
                href={`/search?q=${tag}`}
                className="block p-2 hover:bg-gray-50 dark:hover:bg-[#202024] rounded-xl font-semibold text-gray-500 dark:text-gray-400"
              >
                #{tag}
              </Link>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
