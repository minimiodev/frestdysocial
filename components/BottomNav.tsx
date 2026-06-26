"use client";

import { useState, useEffect } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { Home, Compass, MessageSquare, Bell, User } from "lucide-react";
import useSWR from "swr";

const fetcher = (url: string) => fetch(url).then((res) => res.json());

export default function BottomNav() {
  const pathname = usePathname();
  const [username, setUsername] = useState<string | null>(null);

  // Lấy thông tin user hiện tại qua SWR
  const { data: userData } = useSWR("/api/auth/me", fetcher);
  
  // Lấy danh sách thông báo để đếm số lượng chưa đọc
  const { data: notificationsData } = useSWR(
    userData?.user ? "/api/notifications" : null,
    fetcher,
    { refreshInterval: 15000 } // Tự động cập nhật sau 15 giây
  );

  // Lấy các nhóm chat để mock badge hộp thư (hoặc dùng API tin nhắn chưa đọc nếu có)
  // Để đơn giản, ta sẽ đếm số tin nhắn chưa đọc hoặc mock một số badge nhỏ
  const unreadNotifications = notificationsData?.notifications?.filter(
    (n: any) => !n.isRead
  )?.length || 0;

  useEffect(() => {
    if (userData?.user) {
      setUsername(userData.user.username);
    }
  }, [userData]);

  if (!userData?.user) return null;

  const navItems = [
    { name: "Trang chủ", href: "/", icon: Home },
    { name: "Khám phá", href: "/explore", icon: Compass },
    { 
      name: "Hộp thư", 
      href: "/chat", 
      icon: MessageSquare,
      badge: 0 // Có thể bổ sung đếm tin nhắn sau
    },
    { 
      name: "Thông báo", 
      href: "/notifications", 
      icon: Bell,
      badge: unreadNotifications
    },
    { 
      name: "Cá nhân", 
      href: username ? `/profile/${username}` : "/login", 
      icon: User 
    },
  ];

  return (
    <nav className="fixed bottom-0 left-0 right-0 h-16 bg-[var(--card-bg)]/80 backdrop-blur-lg border-t border-[var(--card-border)] flex items-center justify-around px-2 z-40 md:hidden shadow-lg transition-transform duration-300">
      {navItems.map((item) => {
        const Icon = item.icon;
        // Kiểm tra active route
        const isActive = 
          item.href === "/" 
            ? pathname === "/" 
            : pathname.startsWith(item.href);

        return (
          <Link
            key={item.name}
            href={item.href}
            className="flex flex-col items-center justify-center flex-1 h-full py-2 relative group"
          >
            <div className={`p-1.5 rounded-xl transition-all duration-200 ${
              isActive 
                ? "text-primary scale-110" 
                : "text-gray-400 group-hover:text-primary dark:text-gray-500"
            }`}>
              <Icon className="w-5.5 h-5.5 stroke-[2.2]" />
            </div>

            {/* Notification Badge */}
            {item.badge && item.badge > 0 ? (
              <span className="absolute top-2 right-1/2 translate-x-4 bg-accent-pink text-white text-[9px] font-extrabold px-1.5 py-0.5 rounded-full ring-2 ring-[var(--card-bg)] shadow-sm animate-pulse">
                {item.badge}
              </span>
            ) : null}

            {/* Micro active bar */}
            {isActive && (
              <span className="absolute bottom-1 w-1.5 h-1.5 bg-primary rounded-full" />
            )}
          </Link>
        );
      })}
    </nav>
  );
}
