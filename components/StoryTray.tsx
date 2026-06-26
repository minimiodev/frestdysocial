"use client";

import { useState } from "react";
import { Plus, X, Heart, Eye } from "lucide-react";

interface StoryItem {
  id: number;
  mediaFilename: string;
  mediaType: string;
  caption?: string | null;
  createdAt: string;
  views: { viewerId: number }[];
  reactions: { userId: number; reactionType: string }[];
}

interface GroupedStory {
  user: {
    id: number;
    username: string;
    fullName: string;
    avatarFilename: string;
  };
  stories: StoryItem[];
}

interface StoryTrayProps {
  groupedStories: GroupedStory[];
  currentUserId?: number;
  onRefresh?: () => void;
}

export default function StoryTray({ groupedStories = [], currentUserId, onRefresh }: StoryTrayProps) {
  const [activeGroupIndex, setActiveGroupIndex] = useState<number | null>(null);
  const [activeStoryIndex, setActiveStoryIndex] = useState<number>(0);
  const [showUploadModal, setShowUploadModal] = useState(false);
  const [caption, setCaption] = useState("");
  const [uploading, setUploading] = useState(false);
  const [uploadedUrl, setUploadedUrl] = useState("");
  const [mediaType, setMediaType] = useState("image");

  // Xử lý tạo story mới
  const handleCreateStory = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!uploadedUrl) return;

    setUploading(true);
    try {
      const res = await fetch("/api/stories", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          mediaFilename: uploadedUrl,
          mediaType: mediaType,
          caption,
        }),
      });

      if (res.ok) {
        setShowUploadModal(false);
        setCaption("");
        setUploadedUrl("");
        if (onRefresh) onRefresh();
      }
    } catch (err) {
      console.error(err);
    } finally {
      setUploading(false);
    }
  };

  // Mở trình xem story
  const openStoryViewer = (groupIndex: number) => {
    setActiveGroupIndex(groupIndex);
    setActiveStoryIndex(0);
    
    // Đăng ký xem story
    const story = groupedStories[groupIndex].stories[0];
    registerStoryView(story.id);
  };

  // Đăng ký xem story
  const registerStoryView = async (storyId: number) => {
    try {
      await fetch(`/api/stories/${storyId}`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "view" }),
      });
    } catch (e) {}
  };

  // React story
  const handleReactStory = async (storyId: number) => {
    try {
      const res = await fetch(`/api/stories/${storyId}`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "react", reactionType: "love" }),
      });
      if (res.ok && onRefresh) {
        onRefresh();
      }
    } catch (e) {}
  };

  const handleNextStory = () => {
    if (activeGroupIndex === null) return;
    const currentGroup = groupedStories[activeGroupIndex];
    if (activeStoryIndex < currentGroup.stories.length - 1) {
      const nextIndex = activeStoryIndex + 1;
      setActiveStoryIndex(nextIndex);
      registerStoryView(currentGroup.stories[nextIndex].id);
    } else {
      // Chuyển sang user tiếp theo
      if (activeGroupIndex < groupedStories.length - 1) {
        const nextGroupIndex = activeGroupIndex + 1;
        setActiveGroupIndex(nextGroupIndex);
        setActiveStoryIndex(0);
        registerStoryView(groupedStories[nextGroupIndex].stories[0].id);
      } else {
        // Hết stories
        setActiveGroupIndex(null);
      }
    }
  };

  const handlePrevStory = () => {
    if (activeGroupIndex === null) return;
    if (activeStoryIndex > 0) {
      const prevIndex = activeStoryIndex - 1;
      setActiveStoryIndex(prevIndex);
      registerStoryView(groupedStories[activeGroupIndex].stories[prevIndex].id);
    } else {
      // Chuyển về user trước đó
      if (activeGroupIndex > 0) {
        const prevGroupIndex = activeGroupIndex - 1;
        setActiveGroupIndex(prevGroupIndex);
        const lastStoryIndex = groupedStories[prevGroupIndex].stories.length - 1;
        setActiveStoryIndex(lastStoryIndex);
        registerStoryView(groupedStories[prevGroupIndex].stories[lastStoryIndex].id);
      } else {
        setActiveGroupIndex(null);
      }
    }
  };

  return (
    <div className="flex gap-4 p-4 bg-[var(--card-bg)] rounded-2xl border border-[var(--card-border)] overflow-x-auto shadow-premium mb-6">
      {/* Create Story Button */}
      <div className="flex flex-col items-center gap-1.5 shrink-0 cursor-pointer" onClick={() => setShowUploadModal(true)}>
        <div className="w-16 h-16 rounded-full bg-gray-100 dark:bg-[#202024] flex items-center justify-center border border-[var(--card-border)] relative shadow-inner hover:scale-102 transition-transform">
          <Plus className="w-6 h-6 text-primary" />
        </div>
        <span className="text-[11px] font-semibold text-gray-500">Tin của bạn</span>
      </div>

      {/* Stories list */}
      {groupedStories.map((group, gIdx) => {
        // Kiểm tra xem user hiện tại đã xem hết stories của group này chưa
        const hasUnviewed = group.stories.some(
          (s) => !s.views.some((v) => v.viewerId === currentUserId)
        );

        return (
          <div key={group.user.id} className="flex flex-col items-center gap-1.5 shrink-0 cursor-pointer" onClick={() => openStoryViewer(gIdx)}>
            <div className={`p-[2.5px] rounded-full ${hasUnviewed ? "bg-gradient-to-tr from-accent-orange via-accent-pink to-accent-purple" : "bg-gray-200 dark:bg-gray-700"}`}>
              <img
                src={`/uploads/avatars/${group.user.avatarFilename}`}
                alt={group.user.fullName}
                className="w-[58px] h-[58px] rounded-full object-cover border-2 border-[var(--card-bg)]"
                onError={(e) => {
                  e.currentTarget.src = "/assets/images/icons/icon-192x192.png";
                }}
              />
            </div>
            <span className="text-[11px] font-medium max-w-[70px] truncate text-center">
              {group.user.id === currentUserId ? "Tin của bạn" : group.user.fullName}
            </span>
          </div>
        );
      })}

      {/* Story Viewer Modal */}
      {activeGroupIndex !== null && (
        <div className="fixed inset-0 bg-black/95 flex items-center justify-center z-50 p-4">
          <button className="absolute top-6 right-6 text-white hover:text-gray-300 z-50 p-2" onClick={() => setActiveGroupIndex(null)}>
            <X className="w-6 h-6" />
          </button>

          {/* Left Arrow */}
          <button className="absolute left-6 top-1/2 -translate-y-1/2 w-10 h-10 bg-white/10 hover:bg-white/20 rounded-full flex items-center justify-center text-white z-50" onClick={handlePrevStory}>
            {"<"}
          </button>

          {/* Story Content Card */}
          <div className="w-full max-w-[420px] aspect-[9/16] bg-gray-900 rounded-3xl relative overflow-hidden flex flex-col justify-between shadow-2xl border border-white/10 p-4">
            {/* Top Bar (Progress indicators & User metadata) */}
            <div className="w-full z-40">
              {/* Progress bars */}
              <div className="flex gap-1.5 w-full mb-3">
                {groupedStories[activeGroupIndex].stories.map((s, idx) => (
                  <div key={s.id} className="h-[3px] bg-white/30 flex-1 rounded-full overflow-hidden">
                    <div
                      className="h-full bg-white transition-all duration-3000"
                      style={{
                        width: idx < activeStoryIndex ? "100%" : idx === activeStoryIndex ? "100%" : "0%",
                        transitionDuration: idx === activeStoryIndex ? "5000ms" : "0ms",
                      }}
                    />
                  </div>
                ))}
              </div>

              {/* User Meta */}
              <div className="flex items-center gap-3">
                <img
                  src={`/uploads/avatars/${groupedStories[activeGroupIndex].user.avatarFilename}`}
                  alt={groupedStories[activeGroupIndex].user.fullName}
                  className="w-9 h-9 rounded-full object-cover ring-2 ring-white/35"
                />
                <div>
                  <h4 className="text-sm font-bold text-white leading-none">
                    {groupedStories[activeGroupIndex].user.fullName}
                  </h4>
                  <span className="text-[10px] text-white/60">
                    {new Date(groupedStories[activeGroupIndex].stories[activeStoryIndex].createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                  </span>
                </div>
              </div>
            </div>

            {/* Media Content */}
            <div className="absolute inset-0 z-10 flex items-center justify-center">
              {groupedStories[activeGroupIndex].stories[activeStoryIndex].mediaType === "video" ? (
                <video
                  src={`/uploads/stories/${groupedStories[activeGroupIndex].stories[activeStoryIndex].mediaFilename}`}
                  autoPlay
                  controls={false}
                  className="w-full h-full object-cover"
                />
              ) : (
                <img
                  src={`/uploads/stories/${groupedStories[activeGroupIndex].stories[activeStoryIndex].mediaFilename}`}
                  alt="Story Content"
                  className="w-full h-full object-cover"
                />
              )}
            </div>

            {/* Bottom Section (Caption & Reactions) */}
            <div className="w-full z-40 flex flex-col gap-3">
              {groupedStories[activeGroupIndex].stories[activeStoryIndex].caption && (
                <p className="text-white text-sm bg-black/40 backdrop-blur-sm p-3 rounded-2xl text-center shadow-lg border border-white/5">
                  {groupedStories[activeGroupIndex].stories[activeStoryIndex].caption}
                </p>
              )}

              <div className="flex justify-between items-center bg-black/35 backdrop-blur-md p-3 rounded-2xl border border-white/5">
                <div className="flex items-center gap-1 text-white/80">
                  <Eye className="w-4 h-4" />
                  <span className="text-xs font-semibold">
                    {groupedStories[activeGroupIndex].stories[activeStoryIndex].views.length} lượt xem
                  </span>
                </div>

                <button
                  onClick={() => handleReactStory(groupedStories[activeGroupIndex].stories[activeStoryIndex].id)}
                  className="w-10 h-10 bg-white/10 hover:bg-white/20 active:scale-95 transition-all rounded-full flex items-center justify-center text-white"
                >
                  <Heart
                    className={`w-5 h-5 ${
                      groupedStories[activeGroupIndex].stories[activeStoryIndex].reactions.some(
                        (r) => r.userId === currentUserId
                      )
                        ? "fill-accent-pink text-accent-pink"
                        : "text-white"
                    }`}
                  />
                </button>
              </div>
            </div>
          </div>

          {/* Right Arrow */}
          <button className="absolute right-6 top-1/2 -translate-y-1/2 w-10 h-10 bg-white/10 hover:bg-white/20 rounded-full flex items-center justify-center text-white z-50" onClick={handleNextStory}>
            {">"}
          </button>
        </div>
      )}

      {/* Upload Story Modal */}
      {showUploadModal && (
        <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4 backdrop-blur-sm">
          <div className="w-full max-w-md bg-[var(--card-bg)] border border-[var(--card-border)] rounded-3xl p-6 shadow-premium">
            <div className="flex justify-between items-center mb-5">
              <h3 className="font-extrabold text-lg">Đăng tin ngắn (Story)</h3>
              <button className="p-1.5 hover:bg-gray-100 dark:hover:bg-[#202024] rounded-xl text-gray-500" onClick={() => setShowUploadModal(false)}>
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleCreateStory} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-gray-400 mb-2 uppercase tracking-wider">
                  Chọn Loại Phương Tiện
                </label>
                <div className="grid grid-cols-2 gap-3">
                  <button
                    type="button"
                    onClick={() => setMediaType("image")}
                    className={`py-2 rounded-xl text-sm font-semibold border ${
                      mediaType === "image"
                        ? "bg-primary/5 text-primary border-primary/30"
                        : "border-gray-200 dark:border-gray-800"
                    }`}
                  >
                    Hình ảnh
                  </button>
                  <button
                    type="button"
                    onClick={() => setMediaType("video")}
                    className={`py-2 rounded-xl text-sm font-semibold border ${
                      mediaType === "video"
                        ? "bg-primary/5 text-primary border-primary/30"
                        : "border-gray-200 dark:border-gray-800"
                    }`}
                  >
                    Video
                  </button>
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-gray-400 mb-2 uppercase tracking-wider">
                  Đường Dẫn Media (R2 / File mẫu)
                </label>
                <input
                  type="text"
                  placeholder="Ví dụ: story_sunset.jpg"
                  required
                  value={uploadedUrl}
                  onChange={(e) => setUploadedUrl(e.target.value)}
                  className="w-full px-4 py-2.5 rounded-xl border border-[var(--card-border)] bg-gray-50 dark:bg-[#18181c] text-sm focus:outline-none focus:border-primary"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-gray-400 mb-2 uppercase tracking-wider">
                  Chú thích (Caption)
                </label>
                <textarea
                  placeholder="Mô tả tin ngắn của bạn..."
                  rows={2}
                  value={caption}
                  onChange={(e) => setCaption(e.target.value)}
                  className="w-full px-4 py-2.5 rounded-xl border border-[var(--card-border)] bg-gray-50 dark:bg-[#18181c] text-sm focus:outline-none focus:border-primary resize-none"
                />
              </div>

              <button
                type="submit"
                disabled={uploading}
                className="w-full py-3 rounded-xl bg-primary hover:bg-primary-hover text-white text-sm font-bold shadow-premium transition-all disabled:opacity-50"
              >
                {uploading ? "Đang xử lý..." : "Chia sẻ ngay"}
              </button>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
