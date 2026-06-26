"use client";

import { useState, useEffect } from "react";
import useSWR from "swr";
import Link from "next/link";
import { 
  Bell, 
  Heart, 
  MessageCircle, 
  UserPlus, 
  Repeat, 
  MessageSquare, 
  Users,
  CheckCheck,
  Trash2,
  ChevronRight
} from "lucide-react";

const fetcher = (url: string) => fetch(url).then((res) => res.json());

export default function NotificationsPage() {
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

  // 2. Fetch notifications
  const { data, mutate, error } = useSWR(
    currentUser ? "/api/notifications" : null,
    fetcher
  );

  // 3. Đánh dấu tất cả là đã đọc
  const handleMarkAllRead = async () => {
    if (!currentUser) return;
    try {
      const res = await fetch("/api/notifications", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
      });
      if (res.ok) {
        mutate();
      }
    } catch (e) {
      console.error(e);
    }
  };

  // 4. Xóa tất cả thông báo
  const handleClearAll = async () => {
    if (!currentUser || !window.confirm("Bạn có chắc chắn muốn xóa toàn bộ thông báo không?")) return;
    try {
      const res = await fetch("/api/notifications", {
        method: "DELETE",
      });
      if (res.ok) {
        mutate();
      }
    } catch (e) {
      console.error(e);
    }
  };

  // 5. Đánh dấu 1 thông báo cụ thể là đã đọc
  const handleMarkAsRead = async (id: number) => {
    try {
      await fetch("/api/notifications", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id }),
      });
      mutate();
    } catch (e) {}
  };

  if (!currentUser) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <div className="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  // Render Icon & Text dựa trên loại thông báo
  const getNotificationDetails = (noti: any) => {
    const senderName = noti.sender ? noti.sender.fullName : "Một người dùng";
    const username = noti.sender ? noti.sender.username : "";
    
    switch (noti.type) {
      case "like":
        return {
          icon: <Heart className="w-4 h-4 text-accent-pink fill-accent-pink" />,
          bgColor: "bg-red-50 dark:bg-red-950/20 text-accent-pink border-red-100 dark:border-red-950/40",
          text: (
            <span>
              <strong className="font-bold text-gray-900 dark:text-gray-100">{senderName}</strong> đã thích bài viết của bạn.
            </span>
          ),
          link: `/profile/${currentUser.username}`, // Link về profile để xem bài viết của mình
        };
      case "reply":
        return {
          icon: <MessageCircle className="w-4 h-4 text-primary fill-primary/10" />,
          bgColor: "bg-blue-50 dark:bg-blue-950/20 text-primary border-blue-100 dark:border-blue-950/40",
          text: (
            <span>
              <strong className="font-bold text-gray-900 dark:text-gray-100">{senderName}</strong> đã bình luận bài viết của bạn.
            </span>
          ),
          link: `/profile/${currentUser.username}`,
        };
      case "follow":
        return {
          icon: <UserPlus className="w-4 h-4 text-accent-green" />,
          bgColor: "bg-emerald-50 dark:bg-emerald-950/20 text-accent-green border-emerald-100 dark:border-emerald-950/40",
          text: (
            <span>
              <strong className="font-bold text-gray-900 dark:text-gray-100">{senderName}</strong> đã bắt đầu theo dõi bạn.
            </span>
          ),
          link: `/profile/${username}`, // Link tới profile người theo dõi
        };
      case "repost":
        return {
          icon: <Repeat className="w-4 h-4 text-accent-purple" />,
          bgColor: "bg-purple-50 dark:bg-purple-950/20 text-accent-purple border-purple-100 dark:border-purple-950/40",
          text: (
            <span>
              <strong className="font-bold text-gray-900 dark:text-gray-100">{senderName}</strong> đã chia sẻ lại bài viết của bạn.
            </span>
          ),
          link: `/profile/${currentUser.username}`,
        };
      case "chat":
        return {
          icon: <MessageSquare className="w-4 h-4 text-accent-orange fill-accent-orange/10" />,
          bgColor: "bg-orange-50 dark:bg-orange-950/20 text-accent-orange border-orange-100 dark:border-orange-950/40",
          text: (
            <span>
              <strong className="font-bold text-gray-900 dark:text-gray-100">{senderName}</strong> đã gửi cho bạn một tin nhắn.
            </span>
          ),
          link: `/chat`, // Link về hộp thư chat
        };
      case "group_invite":
        return {
          icon: <Users className="w-4 h-4 text-primary" />,
          bgColor: "bg-indigo-50 dark:bg-indigo-950/20 text-primary border-indigo-100 dark:border-indigo-950/40",
          text: (
            <span>
              <strong className="font-bold text-gray-900 dark:text-gray-100">{senderName}</strong> đã mời bạn tham gia nhóm trò chuyện.
            </span>
          ),
          link: `/chat`,
        };
      default:
        return {
          icon: <Bell className="w-4 h-4 text-gray-500" />,
          bgColor: "bg-gray-50 dark:bg-gray-950/20 text-gray-500 border-gray-100 dark:border-gray-950/40",
          text: <span>Bạn có thông báo mới từ hệ thống.</span>,
          link: `/`,
        };
    }
  };

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      {/* Header */}
      <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl p-5 shadow-premium flex flex-col sm:flex-row sm:items-center justify-between gap-4 relative overflow-hidden">
        <div className="absolute -right-6 -bottom-6 w-24 h-24 bg-primary/5 rounded-full blur-2xl" />
        <div className="flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary shrink-0 shadow-sm animate-pulse">
            <Bell className="w-6 h-6 fill-primary" />
          </div>
          <div>
            <h2 className="font-extrabold text-lg">Thông báo</h2>
            <p className="text-xs text-gray-400 font-medium">Cập nhật tương tác và tin nhắn thời gian thực</p>
          </div>
        </div>

        {/* Action Buttons */}
        {data?.notifications && data.notifications.length > 0 && (
          <div className="flex items-center gap-2 self-end sm:self-center">
            <button
              onClick={handleMarkAllRead}
              className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-[var(--card-border)] hover:bg-gray-50 dark:hover:bg-[#202024] text-[10.5px] font-bold text-gray-600 dark:text-gray-300 transition-colors"
            >
              <CheckCheck className="w-3.5 h-3.5" />
              Đọc tất cả
            </button>
            <button
              onClick={handleClearAll}
              className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-red-200 dark:border-red-950 hover:bg-red-50 dark:hover:bg-red-950/10 text-[10.5px] font-bold text-accent-pink transition-colors"
            >
              <Trash2 className="w-3.5 h-3.5" />
              Xóa hết
            </button>
          </div>
        )}
      </div>

      {/* Notifications List */}
      <div className="space-y-3">
        {data?.notifications ? (
          data.notifications.length > 0 ? (
            data.notifications.map((noti: any) => {
              const details = getNotificationDetails(noti);
              return (
                <div
                  key={noti.id}
                  onClick={() => handleMarkAsRead(noti.id)}
                  className={`group bg-[var(--card-bg)] border rounded-2xl p-4 shadow-sm hover:shadow-md transition-all flex items-center justify-between gap-4 cursor-pointer relative overflow-hidden ${
                    !noti.isRead 
                      ? "border-primary/20 bg-primary/[0.01] dark:bg-primary/[0.005]" 
                      : "border-[var(--card-border)]"
                  }`}
                >
                  {/* Unread indicator bar */}
                  {!noti.isRead && (
                    <div className="absolute left-0 top-0 bottom-0 w-1 bg-primary" />
                  )}

                  <div className="flex items-center gap-3.5 overflow-hidden flex-1">
                    {/* Icon wrapper */}
                    <div className={`w-8.5 h-8.5 rounded-xl border flex items-center justify-center shrink-0 ${details.bgColor}`}>
                      {details.icon}
                    </div>

                    {/* Sender avatar */}
                    {noti.sender && (
                      <img
                        src={`/uploads/avatars/${noti.sender.avatarFilename}`}
                        alt={noti.sender.fullName}
                        className="w-10 h-10 rounded-xl object-cover shrink-0 ring-2 ring-gray-100 dark:ring-[#202024]"
                        onError={(e) => {
                          e.currentTarget.src = "/assets/images/icons/icon-192x192.png";
                        }}
                      />
                    )}

                    {/* Notification content */}
                    <div className="overflow-hidden pr-2">
                      <p className="text-xs text-gray-600 dark:text-gray-300 font-medium leading-relaxed">
                        {details.text}
                      </p>
                      <span className="text-[9.5px] text-gray-400 font-medium mt-1 block">
                        {new Date(noti.createdAt).toLocaleDateString("vi-VN", {
                          hour: "2-digit",
                          minute: "2-digit",
                          day: "numeric",
                          month: "short",
                        })}
                      </span>
                    </div>
                  </div>

                  {/* Navigation click item */}
                  <Link
                    href={details.link}
                    className="p-2 rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-[#1a1a1f] dark:hover:bg-[#222228] transition-colors shrink-0 text-gray-400 group-hover:text-primary"
                  >
                    <ChevronRight className="w-4 h-4" />
                  </Link>
                </div>
              );
            })
          ) : (
            <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl p-12 text-center text-gray-400 font-medium text-xs space-y-2">
              <Bell className="w-8 h-8 mx-auto opacity-30 text-gray-400" />
              <p className="font-bold text-gray-600 dark:text-gray-300">Không có thông báo mới</p>
              <p className="text-[10px] text-gray-400">Các tương tác xã hội của bạn sẽ hiển thị tại đây.</p>
            </div>
          )
        ) : error ? (
          <p className="text-center text-xs text-gray-400 py-6">Không thể kết nối cơ sở dữ liệu.</p>
        ) : (
          // Shimmer loading
          <div className="space-y-3">
            {[1, 2, 3].map((n) => (
              <div key={n} className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl p-4 flex items-center gap-3">
                <div className="w-9 h-9 rounded-xl animate-shimmer shrink-0" />
                <div className="w-10 h-10 rounded-xl animate-shimmer shrink-0" />
                <div className="space-y-1.5 flex-1 py-1">
                  <div className="h-3 w-44 rounded animate-shimmer" />
                  <div className="h-2.5 w-16 rounded animate-shimmer" />
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
