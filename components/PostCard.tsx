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
  Pin
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

  // Bắt đầu viết card
  const isPostAuthor = post.userId === currentUserId;
  const isNsfwHidden = post.isNsfw && !nsfwRevealed && !isAdult;

  // Lấy badge xác minh
  const getBadgeIcon = (type: string) => {
    switch (type) {
      case "official":
        return <span className="text-[#1877f2]" title="Đã xác minh">✓</span>;
      case "subscribed":
        return <span className="text-primary-dark" title="Frest Subscribed">✓</span>;
      case "developer":
        return <span className="text-accent-purple" title="Nhà phát triển">⚙</span>;
      case "business":
        return <span className="text-accent-green" title="Doanh nghiệp">💼</span>;
      default:
        return null;
    }
  };

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
      <div className="flex items-center gap-3 mb-4.5">
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
            <p className="text-[13.5px] leading-relaxed whitespace-pre-wrap">{post.content}</p>

            {/* Media Attachment */}
            {post.imageFilename && (
              <div className="rounded-2xl overflow-hidden border border-[var(--card-border)] bg-black/5 dark:bg-white/5">
                <img
                  src={`/uploads/posts/${post.imageFilename}`}
                  alt="Post attachment"
                  className="w-full h-auto max-h-[480px] object-cover"
                />
              </div>
            )}

            {post.videoFilename && (
              <div className="rounded-2xl overflow-hidden border border-[var(--card-border)] bg-black relative">
                <video
                  src={`/uploads/posts/${post.videoFilename}`}
                  controls
                  className="w-full max-h-[400px] object-contain"
                />
              </div>
            )}

            {post.audioFilename && (
              <div className="flex items-center gap-3 p-3 bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-2xl">
                <div className="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                  <Music className="w-5 h-5" />
                </div>
                <audio src={`/uploads/posts/${post.audioFilename}`} controls className="flex-1 h-8" />
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
                        <Link href={`/profile/${reply.user.username}`} className="font-bold text-xs hover:underline">
                          {reply.user.fullName}
                        </Link>
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
                          <Link href={`/profile/${child.user.username}`} className="font-bold text-[11px] hover:underline">
                            {child.user.fullName}
                          </Link>
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
    </div>
  );
}
