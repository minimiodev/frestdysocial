"use client";

import { useState } from "react";
import { 
  MessageCircle, 
  Share2, 
  Bookmark, 
  MoreHorizontal, 
  Play, 
  Music, 
  Download, 
  Lock, 
  Check, 
  ShieldAlert,
  Send,
  Pin,
  Edit,
  Trash2
} from "lucide-react";
import Link from "next/link";

interface PostCardProps {
  post: any;
  currentUserId?: number;
  isAdult?: boolean;
  onRefresh?: () => void;
}

export default function PostCard({ post, currentUserId, isAdult = false, onRefresh }: PostCardProps) {
  const [showComments, setShowComments] = useState(false);
  const [replies, setReplies] = useState<any[]>(post.replies || []);
  const [newComment, setNewComment] = useState("");
  const [activeReaction, setActiveReaction] = useState<string | null>(() => {
    const mine = post.reactions?.find((r: any) => r.userId === currentUserId);
    return mine ? mine.reactionType : null;
  });
  const [showReactionPicker, setShowReactionPicker] = useState(false);
  const [nsfwRevealed, setNsfwRevealed] = useState(false);
  const [voting, setVoting] = useState(false);
  const [showMenu, setShowMenu] = useState(false);
  const [showReportModal, setShowReportModal] = useState(false);
  const [reportReason, setReportReason] = useState("Nội dung nhạy cảm");
  const [reportDetails, setReportDetails] = useState("");
  const [reporting, setReporting] = useState(false);

  const [isEditing, setIsEditing] = useState(false);
  const [editContent, setEditContent] = useState(post.content);
  const [saving, setSaving] = useState(false);
  const [isDeleted, setIsDeleted] = useState(false);

  // Lưu nội dung sửa đổi
  const handleSaveEdit = async () => {
    if (!editContent.trim()) return;
    setSaving(true);
    try {
      const res = await fetch(`/api/posts/${post.id}`, {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ content: editContent }),
      });
      if (res.ok) {
        setIsEditing(false);
        post.content = editContent; // Cập nhật trực tiếp trên UI
        if (onRefresh) onRefresh();
      } else {
        alert("Gặp lỗi khi lưu chỉnh sửa bài đăng.");
      }
    } catch (e) {
      console.error(e);
      alert("Lỗi kết nối khi lưu bài đăng.");
    } finally {
      setSaving(false);
    }
  };

  // Bật/Tắt ghim bài viết
  const handleTogglePin = async () => {
    try {
      const res = await fetch(`/api/posts/${post.id}`, {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ isPinned: !post.isPinned }),
      });
      if (res.ok) {
        setShowMenu(false);
        if (onRefresh) onRefresh();
      } else {
        alert("Gặp lỗi khi ghim/bỏ ghim bài đăng.");
      }
    } catch (e) {
      console.error(e);
    }
  };

  // Xóa bài viết
  const handleDeletePost = async () => {
    if (!confirm("Bạn có chắc chắn muốn xóa bài viết này vĩnh viễn không?")) return;
    try {
      const res = await fetch(`/api/posts/${post.id}`, {
        method: "DELETE",
      });
      if (res.ok) {
        setIsDeleted(true);
        setShowMenu(false);
        if (onRefresh) onRefresh();
      } else {
        alert("Gặp lỗi khi xóa bài đăng.");
      }
    } catch (e) {
      console.error(e);
    }
  };

  const reactionEmojis: Record<string, string> = {
    like: "👍",
    love: "❤️",
    haha: "😆",
    wow: "😮",
    sad: "😢",
    angry: "😡",
  };

  const reactionNames: Record<string, string> = {
    like: "Thích",
    love: "Yêu thích",
    haha: "Haha",
    wow: "Wow",
    sad: "Buồn",
    angry: "Phẫn nộ",
  };

  // Cảm xúc
  const handleReact = async (type: string) => {
    try {
      const res = await fetch(`/api/posts/${post.id}/react`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ reactionType: type }),
      });
      if (res.ok) {
        const data = await res.json();
        setActiveReaction(data.action === "removed" ? null : type);
        setShowReactionPicker(false);
        if (onRefresh) onRefresh();
      }
    } catch (e) {
      console.error(e);
    }
  };

  // Viết bình luận
  const handleAddComment = async (e: React.FormEvent, parentReplyId?: number) => {
    e.preventDefault();
    if (!newComment.trim()) return;

    try {
      const res = await fetch(`/api/posts/${post.id}/reply`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ content: newComment, parentReplyId }),
      });
      if (res.ok) {
        setNewComment("");
        const data = await res.json();
        setReplies([data.reply, ...replies]);
        if (onRefresh) onRefresh();
      }
    } catch (e) {
      console.error(e);
    }
  };

  // Vote poll
  const handleVotePoll = async (optionId: number) => {
    if (!currentUserId || voting) return;
    setVoting(true);
    try {
      // Vì poll_votes sử dụng unique composite key [pollId, userId]
      // Nên chúng ta gọi API để vote. Để đơn giản, ta viết logic vote ở client/server
      // Ở đây ta mô phỏng bằng một cuộc gọi API
      const res = await fetch(`/api/posts/poll-vote`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ optionId, pollId: post.polls[0].id }),
      });
      if (res.ok && onRefresh) {
        onRefresh();
      }
    } catch (e) {}
    finally {
      setVoting(false);
    }
  };

  // Bookmark
  const handleBookmark = async () => {
    try {
      const res = await fetch(`/api/posts/${post.id}/bookmark`, {
        method: "POST",
      });
      if (res.ok && onRefresh) {
        onRefresh();
      }
    } catch (e) {}
  };

  // Báo cáo bài viết
  const handleReportPost = async (e: React.FormEvent) => {
    e.preventDefault();
    if (reporting) return;
    setReporting(true);
    try {
      const res = await fetch(`/api/posts/${post.id}/report`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ reason: reportReason, details: reportDetails }),
      });
      if (res.ok) {
        alert("Cảm ơn bạn! Báo cáo của bạn đã được ghi nhận và đang chờ ban quản trị xét duyệt.");
        setShowReportModal(false);
        setReportDetails("");
      } else {
        const errData = await res.json();
        alert(errData.error || "Gửi báo cáo thất bại.");
      }
    } catch (err) {
      console.error(err);
      alert("Đã xảy ra lỗi khi gửi báo cáo.");
    } finally {
      setReporting(false);
    }
  };

  // Bắt đầu viết card
  const isPostAuthor = post.userId === currentUserId;
  const isEditable = post.userId === currentUserId || (post.page && post.page.ownerId === currentUserId);
  const isNsfwHidden = post.isNsfw && !nsfwRevealed && !isAdult;

  // Lấy badge xác minh
  const getBadgeIcon = (type: string) => {
    switch (type) {
      case "official":
        return (
          <svg className="w-4 h-4 inline-block shrink-0" viewBox="0 0 24 24" title="Đã xác minh">
            <g fillRule="evenodd" transform="translate(-92)">
              <path fill="#1877f2" d="m115.887 14.475-1.269-2.475 1.267-2.474a1.02 1.02 0 0 0-.355-1.326l-2.334-1.51-.14-2.775a1.018 1.018 0 0 0-.97-.971l-2.778-.14-1.51-2.336a1.02 1.02 0 0 0-1.324-.354L104 1.38 101.526.114a1.02 1.02 0 0 0-1.326.354l-1.509 2.336-2.777.14a1.017 1.017 0 0 0-.97.97l-.14 2.777L92.468 8.2a1.02 1.02 0 0 0-.354 1.325L93.382 12l-1.268 2.474a1.02 1.02 0 0 0 .355 1.326l2.335 1.509.14 2.776c.025.528.443.945.97.971l2.777.14 1.51 2.336a1.02 1.02 0 0 0 1.324.354L104 22.62l2.474 1.267c.469.242 1.039.09 1.326-.355l1.51-2.335 2.776-.14c.527-.026.945-.443.97-.97l.14-2.777 2.336-1.51c.443-.286.595-.856.354-1.324" />
              <path fill="#ffffff" d="m109.207 9.707-6.5 6.5a.996.996 0 0 1-1.414 0l-3-3a1 1 0 1 1 1.414-1.414L102 14.086l5.793-5.793a1 1 0 1 1 1.414 1.414" />
            </g>
          </svg>
        );
      case "subscribed":
        return (
          <svg className="w-4 h-4 text-amber-500 fill-current inline-block shrink-0" viewBox="0 0 24 24" title="Frest Subscribed">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H7c0-2.76 2.24-5 5-5s5 2.24 5 5c0 1.04-.42 1.99-1.07 2.75z" />
          </svg>
        );
      case "developer":
        return (
          <span className="text-[10px] bg-purple-500/10 text-purple-500 border border-purple-500/20 px-1.5 py-0.5 rounded-full font-bold flex items-center gap-0.5" title="Nhà phát triển">
            ⚙ Dev
          </span>
        );
      case "business":
        return (
          <span className="text-[10px] bg-green-500/10 text-green-500 border border-green-500/20 px-1.5 py-0.5 rounded-full font-bold flex items-center gap-0.5" title="Doanh nghiệp">
            💼 Biz
          </span>
        );
      default:
        return null;
    }
  };

  if (isDeleted) return null;

  return (
    <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl p-4 mb-4 shadow-premium relative transition-all duration-200">
      {/* Pinned badge */}
      {post.isPinned && (
        <div className="absolute top-4 right-4 flex items-center gap-1 text-primary text-[10px] font-bold uppercase tracking-wider bg-primary/5 px-2 py-0.5 rounded-full">
          <Pin className="w-3 h-3 fill-primary" />
          Ghim bài viết
        </div>
      )}

      {/* Header Info */}
      <div className="flex items-center justify-between mb-4.5">
        <div className="flex items-center gap-3">
          <img
            src={`/uploads/avatars/${post.page ? post.page.avatarFilename : post.user.avatarFilename}`}
            alt="Author avatar"
            className="w-11 h-11 rounded-xl object-cover"
            onError={(e) => {
              e.currentTarget.src = "/assets/images/icons/icon-192x192.png";
            }}
          />
          <div>
            <div className="flex items-center gap-1.5">
              <Link
                href={post.page ? `/page/${post.page.pageUsername}` : `/profile/${post.user.username}`}
                className="font-bold text-sm hover:underline"
              >
                {post.page ? post.page.pageName : post.user.fullName}
              </Link>
              {getBadgeIcon(post.page ? "official" : post.user.verificationType)}
            </div>
            <span className="text-[10.5px] text-gray-400 font-medium">
              {new Date(post.createdAt).toLocaleDateString("vi-VN", {
                hour: "2-digit",
                minute: "2-digit",
                day: "numeric",
                month: "short",
              })}
            </span>
          </div>
        </div>

        {/* Menu 3 chấm và Pinned badge */}
        <div className="flex items-center gap-2">
          {post.isPinned && (
            <div className="flex items-center gap-1 text-primary text-[10px] font-bold uppercase tracking-wider bg-primary/5 px-2 py-0.5 rounded-full">
              <Pin className="w-3 h-3 fill-primary" />
              Ghim
            </div>
          )}
          
          {/* Nút dropdown menu 3 chấm */}
          <div className="relative">
            <button
              type="button"
              onClick={() => setShowMenu(!showMenu)}
              className="p-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-[#202024] text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors"
            >
              <MoreHorizontal className="w-5 h-5" />
            </button>
            {showMenu && (
              <div className="absolute right-0 mt-1 w-48 bg-[var(--card-bg)] border border-[var(--card-border)] rounded-xl py-1.5 shadow-premium z-30 space-y-0.5">
                {isEditable && (
                  <>
                    <button
                      type="button"
                      onClick={() => {
                        setIsEditing(true);
                        setShowMenu(false);
                      }}
                      className="w-full text-left px-4 py-2 text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-[#202024] transition-colors flex items-center gap-2"
                    >
                      <Edit className="w-4 h-4 text-gray-400" />
                      Sửa nội dung
                    </button>
                    <button
                      type="button"
                      onClick={handleTogglePin}
                      className="w-full text-left px-4 py-2 text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-[#202024] transition-colors flex items-center gap-2"
                    >
                      <Pin className="w-4 h-4 text-gray-400" />
                      {post.isPinned ? "Bỏ ghim bài viết" : "Ghim bài viết"}
                    </button>
                    <div className="h-px bg-[var(--card-border)] my-1" />
                    <button
                      type="button"
                      onClick={handleDeletePost}
                      className="w-full text-left px-4 py-2 text-xs font-semibold text-red-500 hover:bg-red-50 dark:hover:bg-red-950/20 transition-colors flex items-center gap-2"
                    >
                      <Trash2 className="w-4 h-4" />
                      Xóa bài viết
                    </button>
                  </>
                )}
                {!isPostAuthor && (
                  <button
                    type="button"
                    onClick={() => {
                      setShowMenu(false);
                      setShowReportModal(true);
                    }}
                    className="w-full text-left px-4 py-2 text-xs font-semibold text-red-500 hover:bg-red-50 dark:hover:bg-red-950/20 transition-colors flex items-center gap-2"
                  >
                    <ShieldAlert className="w-4 h-4" />
                    Báo cáo bài viết
                  </button>
                )}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Content */}
      <div className="space-y-4">
        {/* NSFW warning */}
        {isNsfwHidden ? (
          <div className="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/30 p-4 rounded-xl text-center space-y-3">
            <div className="flex items-center justify-center gap-2 text-accent-pink">
              <ShieldAlert className="w-5 h-5" />
              <span className="font-bold text-xs uppercase tracking-wide">Nội dung 18+ (NSFW)</span>
            </div>
            <p className="text-xs text-gray-500 dark:text-gray-400">
              Bài đăng này chứa nội dung nhạy cảm. Bạn phải trên 18 tuổi hoặc đã xác minh độ tuổi để xem.
            </p>
            <button
              onClick={() => setNsfwRevealed(true)}
              className="px-4 py-2 bg-accent-pink text-white rounded-xl text-xs font-bold hover:bg-red-600 transition-all shadow-md"
            >
              Cố chấp hiển thị
            </button>
          </div>
        ) : (
          <>
            {/* Text content */}
            {isEditing ? (
              <div className="space-y-3 p-3 bg-gray-50 dark:bg-[#18181c] rounded-2xl border border-[var(--card-border)]">
                <textarea
                  value={editContent}
                  onChange={(e) => setEditContent(e.target.value)}
                  className="w-full bg-transparent border-none text-xs placeholder-gray-400 focus:ring-0 resize-none focus:outline-none min-h-[80px]"
                  placeholder="Nhập nội dung bài viết..."
                />
                <div className="flex gap-2 justify-end">
                  <button
                    type="button"
                    onClick={() => {
                      setIsEditing(false);
                      setEditContent(post.content);
                    }}
                    disabled={saving}
                    className="px-3.5 py-1.5 bg-gray-200 hover:bg-gray-300 dark:bg-[#202024] dark:hover:bg-[#2a2a30] text-gray-500 rounded-xl text-[11px] font-bold transition-all"
                  >
                    Hủy
                  </button>
                  <button
                    type="button"
                    onClick={handleSaveEdit}
                    disabled={saving || !editContent.trim()}
                    className="px-4 py-1.5 bg-primary hover:bg-primary-hover text-white rounded-xl text-[11px] font-bold transition-all flex items-center gap-1.5 shadow-md disabled:opacity-50"
                  >
                    {saving && <span className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />}
                    Lưu
                  </button>
                </div>
              </div>
            ) : (
              <p className="text-[13.5px] leading-relaxed whitespace-pre-wrap">{post.content}</p>
            )}

            {/* Media Attachment */}
            {post.imageFilename && (
              <div className="rounded-2xl overflow-hidden border border-[var(--card-border)] bg-black/5 dark:bg-white/5">
                <img
                  src={post.imageFilename.startsWith("http") ? post.imageFilename : `/uploads/posts/${post.imageFilename}`}
                  alt="Post attachment"
                  className="w-full h-auto max-h-[480px] object-cover"
                />
              </div>
            )}

            {post.videoFilename && (
              <div className="rounded-2xl overflow-hidden border border-[var(--card-border)] bg-black/90 dark:bg-black/95 relative shadow-inner aspect-video flex items-center justify-center">
                <video
                  src={post.videoFilename.startsWith("http") ? post.videoFilename : `/uploads/posts/${post.videoFilename}`}
                  controls
                  preload="metadata"
                  playsInline
                  className="w-full h-full max-h-[480px] object-contain rounded-2xl"
                />
              </div>
            )}

            {post.audioFilename && (
              <div className="flex items-center gap-3 p-3 bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-2xl">
                <div className="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                  <Music className="w-5 h-5" />
                </div>
                <audio src={post.audioFilename.startsWith("http") ? post.audioFilename : `/uploads/posts/${post.audioFilename}`} controls className="flex-1 h-8" />
              </div>
            )}

            {post.documentFilename && (
              <div className="flex items-center justify-between p-3.5 bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-2xl">
                <span className="text-xs font-bold text-gray-500 truncate max-w-[200px]">
                  📄 {post.documentFilename}
                </span>
                <a
                  href={`/uploads/posts/${post.documentFilename}`}
                  download
                  className="p-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-[#202024] dark:hover:bg-[#2a2a30] transition-colors"
                >
                  <Download className="w-4 h-4 text-primary" />
                </a>
              </div>
            )}

            {/* Poll Component */}
            {post.polls && post.polls.length > 0 && (
              <div className="p-4 bg-gray-50 dark:bg-[#1c1c20] rounded-2xl border border-[var(--card-border)] space-y-3">
                <h4 className="font-bold text-sm">📊 {post.polls[0].question}</h4>
                <div className="space-y-2">
                  {post.polls[0].options.map((opt: any) => {
                    const totalVotes = post.polls[0].options.reduce(
                      (acc: number, o: any) => acc + o.votes.length,
                      0
                    );
                    const optionVotes = opt.votes.length;
                    const percent = totalVotes > 0 ? Math.round((optionVotes / totalVotes) * 100) : 0;
                    const hasVoted = opt.votes.some((v: any) => v.userId === currentUserId);

                    return (
                      <div
                        key={opt.id}
                        onClick={() => handleVotePoll(opt.id)}
                        className={`relative p-3 rounded-xl border border-[var(--card-border)] cursor-pointer overflow-hidden transition-all hover:bg-gray-100 dark:hover:bg-[#222228] ${
                          hasVoted ? "border-primary/50" : ""
                        }`}
                      >
                        {/* Progress Bar background */}
                        <div
                          className="absolute inset-y-0 left-0 bg-primary/10 transition-all duration-500"
                          style={{ width: `${percent}%` }}
                        />
                        <div className="relative flex justify-between items-center text-xs font-semibold">
                          <span className="flex items-center gap-2">
                            {opt.optionText}
                            {hasVoted && <Check className="w-3.5 h-3.5 text-primary" />}
                          </span>
                          <span>
                            {percent}% ({optionVotes})
                          </span>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            )}

            {/* Repost content */}
            {post.repostOf && (
              <div className="p-4 bg-gray-50/50 dark:bg-[#131316] border border-[var(--card-border)] rounded-2xl space-y-2">
                <div className="flex items-center gap-2">
                  <span className="text-[10px] text-primary font-extrabold uppercase tracking-wider">
                    🔄 Đã chia sẻ lại
                  </span>
                  <span className="text-[11px] text-gray-400 font-bold">
                    @{post.repostOf.user.username}
                  </span>
                </div>
                <p className="text-xs text-gray-500 dark:text-gray-400 italic">
                  {post.repostOf.content}
                </p>
              </div>
            )}

            {/* Link Preview content */}
            {post.linkPreviewUrl && (
              <a
                href={post.linkPreviewUrl}
                target="_blank"
                rel="noreferrer"
                className="flex flex-col md:flex-row rounded-2xl overflow-hidden border border-[var(--card-border)] bg-gray-50 dark:bg-[#18181c] hover:bg-gray-100 dark:hover:bg-[#202024] transition-all"
              >
                {post.linkPreviewImage && (
                  <img
                    src={post.linkPreviewImage}
                    alt="Link preview"
                    className="w-full md:w-36 h-28 object-cover"
                  />
                )}
                <div className="p-3 flex flex-col justify-center gap-1 overflow-hidden">
                  <h5 className="font-bold text-xs truncate text-primary">{post.linkPreviewTitle}</h5>
                  <p className="text-[11px] text-gray-400 line-clamp-2">{post.linkPreviewDesc}</p>
                  <span className="text-[10px] text-gray-400 font-medium truncate">{post.linkPreviewUrl}</span>
                </div>
              </a>
            )}
          </>
        )}
      </div>

      {/* Footer Actions */}
      <div className="flex items-center justify-between border-t border-[var(--card-border)] mt-4 pt-3 text-gray-500">
        {/* Reactions */}
        <div className="relative">
          <button
            onMouseEnter={() => setShowReactionPicker(true)}
            onClick={() => handleReact("like")}
            className={`flex items-center gap-2 px-3 py-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-[#202024] text-xs font-bold transition-all ${
              activeReaction ? "text-primary" : ""
            }`}
          >
            <span>{activeReaction ? reactionEmojis[activeReaction] : "👍"}</span>
            <span>{activeReaction ? reactionNames[activeReaction] : "Thích"}</span>
            {post._count?.reactions > 0 && (
              <span className="bg-gray-100 dark:bg-[#202024] px-1.5 py-0.5 rounded-full text-[10px] font-extrabold">
                {post._count.reactions}
              </span>
            )}
          </button>

          {/* Floating Reaction Picker */}
          {showReactionPicker && (
            <div
              onMouseLeave={() => setShowReactionPicker(false)}
              className="absolute bottom-10 left-0 bg-[var(--card-bg)] border border-[var(--card-border)] rounded-full p-1.5 flex gap-2 shadow-premium animate-in fade-in slide-in-from-bottom-2 duration-150 z-20"
            >
              {Object.keys(reactionEmojis).map((type) => (
                <button
                  key={type}
                  onClick={() => handleReact(type)}
                  className="w-8 h-8 rounded-full hover:scale-120 active:scale-95 transition-all text-lg flex items-center justify-center"
                  title={reactionNames[type]}
                >
                  {reactionEmojis[type]}
                </button>
              ))}
            </div>
          )}
        </div>

        {/* Comments Toggle */}
        <button
          onClick={() => setShowComments(!showComments)}
          className="flex items-center gap-2 px-3 py-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-[#202024] text-xs font-bold transition-all"
        >
          <MessageCircle className="w-4 h-4" />
          <span>Bình luận</span>
          {post._count?.replies > 0 && (
            <span className="bg-gray-100 dark:bg-[#202024] px-1.5 py-0.5 rounded-full text-[10px] font-extrabold">
              {post._count.replies}
            </span>
          )}
        </button>

        {/* Bookmark Toggle */}
        <button
          onClick={handleBookmark}
          className="flex items-center gap-2 px-3 py-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-[#202024] text-xs font-bold transition-all"
        >
          <Bookmark className={`w-4 h-4 ${post.bookmarks?.some((b: any) => b.userId === currentUserId) ? "fill-primary text-primary" : ""}`} />
          <span>Lưu lại</span>
        </button>
      </div>

      {/* Comments section */}
      {showComments && (
        <div className="border-t border-[var(--card-border)] mt-4 pt-4 space-y-4">
          {/* Write comment form */}
          {currentUserId && (
            <form onSubmit={(e) => handleAddComment(e)} className="flex gap-2">
              <input
                type="text"
                placeholder="Nhập bình luận của bạn..."
                value={newComment}
                onChange={(e) => setNewComment(e.target.value)}
                className="flex-1 bg-gray-100 dark:bg-[#18181c] border border-transparent rounded-xl px-4 py-2 text-xs focus:outline-none focus:border-primary focus:bg-white dark:focus:bg-[#1c1c20] transition-colors"
              />
              <button
                type="submit"
                className="w-9 h-9 rounded-xl bg-primary text-white flex items-center justify-center hover:bg-primary-hover active:scale-95 transition-all shadow-md"
              >
                <Send className="w-4 h-4" />
              </button>
            </form>
          )}

          {/* Comments tree */}
          <div className="space-y-3.5 max-h-[300px] overflow-y-auto pr-1">
            {replies.length > 0 ? (
              replies.map((reply: any) => (
                <div key={reply.id} className="space-y-2">
                  <div className="flex gap-2.5">
                    <img
                      src={`/uploads/avatars/${reply.user.avatarFilename}`}
                      alt="avatar"
                      className="w-8 h-8 rounded-lg object-cover"
                    />
                    <div className="flex-1 bg-gray-50 dark:bg-[#1c1c20] p-2.5 rounded-2xl border border-[var(--card-border)]">
                      <div className="flex justify-between items-center mb-0.5">
                        <div className="flex items-center gap-1">
                          <Link href={`/profile/${reply.user.username}`} className="font-bold text-xs hover:underline">
                            {reply.user.fullName}
                          </Link>
                          {reply.user.verificationType === "official" && getBadgeIcon("official")}
                        </div>
                        <span className="text-[9px] text-gray-400 font-medium">
                          {new Date(reply.createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                        </span>
                      </div>
                      <p className="text-xs text-gray-600 dark:text-gray-300 leading-normal">{reply.content}</p>
                    </div>
                  </div>

                  {/* Child replies (comment loop) */}
                  {reply.childReplies && reply.childReplies.map((child: any) => (
                    <div key={child.id} className="flex gap-2.5 ml-8 pl-2 border-l border-gray-200 dark:border-gray-800">
                      <img
                        src={`/uploads/avatars/${child.user.avatarFilename}`}
                        alt="avatar"
                        className="w-7 h-7 rounded-lg object-cover"
                      />
                      <div className="flex-1 bg-gray-100/50 dark:bg-[#151518] p-2 py-1.5 rounded-xl border border-[var(--card-border)]">
                        <div className="flex justify-between items-center mb-0.5">
                          <div className="flex items-center gap-1">
                            <Link href={`/profile/${child.user.username}`} className="font-bold text-[11px] hover:underline">
                              {child.user.fullName}
                            </Link>
                            {child.user.verificationType === "official" && getBadgeIcon("official")}
                          </div>
                          <span className="text-[8px] text-gray-400 font-medium">
                            {new Date(child.createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                          </span>
                        </div>
                        <p className="text-[11px] text-gray-500 dark:text-gray-400 leading-normal">{child.content}</p>
                      </div>
                    </div>
                  ))}
                </div>
              ))
            ) : (
              <p className="text-center text-xs text-gray-400 font-medium py-3">Chưa có bình luận nào. Hãy bắt đầu cuộc trò chuyện!</p>
            )}
          </div>
        </div>
      )}

      {/* Modal Báo cáo */}
      {showReportModal && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl w-full max-w-md overflow-hidden shadow-premium animate-in fade-in zoom-in-95 duration-200">
            <div className="p-5 border-b border-[var(--card-border)] flex items-center justify-between">
              <h3 className="font-bold text-sm text-gray-800 dark:text-gray-100 flex items-center gap-2">
                <ShieldAlert className="text-red-500 w-5 h-5" />
                Báo cáo bài viết vi phạm
              </h3>
              <button
                type="button"
                onClick={() => setShowReportModal(false)}
                className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg font-bold"
              >
                ✕
              </button>
            </div>
            <form onSubmit={handleReportPost} className="p-5 space-y-4">
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-gray-400">Lý do báo cáo</label>
                <select
                  value={reportReason}
                  onChange={(e) => setReportReason(e.target.value)}
                  className="w-full bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-xl px-3 py-2.5 text-xs font-semibold focus:outline-none focus:border-primary transition-colors text-gray-800 dark:text-gray-100"
                >
                  <option value="Nội dung nhạy cảm">Nội dung nhạy cảm / 18+</option>
                  <option value="Vi phạm bản quyền">Vi phạm bản quyền</option>
                  <option value="Quấy rối">Quấy rối / Đe dọa</option>
                  <option value="Spam hoặc lừa đảo">Spam hoặc lừa đảo</option>
                  <option value="Khác">Lý do khác</option>
                </select>
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold text-gray-400">Chi tiết bổ sung (tùy chọn)</label>
                <textarea
                  placeholder="Mô tả cụ thể vi phạm giúp quản trị viên dễ dàng xử lý..."
                  value={reportDetails}
                  onChange={(e) => setReportDetails(e.target.value)}
                  rows={4}
                  className="w-full bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-xl p-3 text-xs focus:outline-none focus:border-primary transition-colors text-gray-800 dark:text-gray-100 resize-none"
                />
              </div>

              <div className="flex gap-3 pt-2">
                <button
                  type="button"
                  onClick={() => setShowReportModal(false)}
                  className="flex-1 py-2.5 rounded-xl border border-[var(--card-border)] hover:bg-gray-50 dark:hover:bg-[#1c1c20] text-xs font-bold transition-colors text-gray-800 dark:text-gray-100"
                >
                  Hủy bỏ
                </button>
                <button
                  type="submit"
                  disabled={reporting}
                  className="flex-1 py-2.5 rounded-xl bg-red-500 hover:bg-red-600 active:scale-95 text-white text-xs font-bold transition-all disabled:opacity-50 shadow-md"
                >
                  {reporting ? "Đang gửi..." : "Gửi báo cáo"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
