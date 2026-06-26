"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import useSWR from "swr";
import { 
  Home, 
  MessageSquare, 
  Bookmark, 
  User, 
  ShieldAlert, 
  LogOut, 
  Sparkles,
  Compass,
  Bell
} from "lucide-react";

const fetcher = (url: string) => fetch(url).then((res) => res.json());

interface SidebarProps {
  currentUser?: {
    username: string;
    fullName: string;
    avatarFilename: string;
  } | null;
  isAdmin?: boolean;
}

export default function Sidebar({ currentUser, isAdmin }: SidebarProps) {
  const pathname = usePathname();
  const router = useRouter();

  // Lấy danh sách thông báo để tính badge động
  const { data: notificationsData } = useSWR(
    currentUser ? "/api/notifications" : null,
    fetcher,
    { refreshInterval: 15000 }
  );

  const unreadNotifications = notificationsData?.notifications?.filter(
    (n: any) => !n.isRead
  )?.length || 0;

  const menuItems = [
    { name: "Trang chủ", href: "/", icon: Home },
    { name: "Khám phá", href: "/explore", icon: Compass },
    { name: "Hộp thư", href: "/chat", icon: MessageSquare },
    { 
      name: "Thông báo", 
      href: "/notifications", 
      icon: Bell, 
      badge: unreadNotifications 
    },
    { name: "Dấu trang", href: "/bookmarks", icon: Bookmark },
  ];

  if (currentUser) {
    menuItems.push({
      name: "Trang cá nhân",
      href: `/profile/${currentUser.username}`,
      icon: User,
    });
  }

  if (isAdmin) {
    menuItems.push({
      name: "Quản trị",
      href: "/admin",
      icon: ShieldAlert,
    });
  }

  const handleLogout = async () => {
    try {
      await fetch("/api/auth/logout", { method: "POST" });
      router.push("/login");
      router.refresh();
    } catch (e) {
      console.error(e);
    }
  };

  return (
    <aside className="fixed left-0 top-0 bottom-0 w-[var(--sidebar-width)] bg-[var(--card-bg)] border-r border-[var(--card-border)] p-6 flex flex-col justify-between hidden md:flex z-40">
      <div>
        {/* Logo */}
        <Link href="/" className="flex items-center gap-3 mb-8 group">
          <div className="w-10 h-10 rounded-xl bg-gradient-to-tr from-primary to-accent-purple flex items-center justify-center shadow-premium transform group-hover:scale-105 transition-all">
            <Sparkles className="w-5 h-5 text-white" />
          </div>
          <div>
            <h1 className="font-extrabold text-xl tracking-tight bg-gradient-to-r from-primary to-accent-purple bg-clip-text text-transparent">
              Frest
            </h1>
            <p className="text-[10px] text-gray-400 font-medium">NEXT GEN SOCIAL</p>
          </div>
        </Link>

        {/* Menu Navigation */}
        <nav className="space-y-1.5">
          {menuItems.map((item) => {
            const Icon = item.icon;
            const isActive = pathname === item.href;
            return (
              <Link
                key={item.name}
                href={item.href}
                className={`flex items-center justify-between px-4 py-3 rounded-xl font-medium text-sm transition-all duration-200 group ${
                  isActive
                    ? "bg-primary text-white shadow-premium"
                    : "text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-[#202024] hover:text-primary dark:hover:text-white"
                }`}
              >
                <div className="flex items-center gap-3.5">
                  <Icon className={`w-5 h-5 transition-transform duration-200 group-hover:scale-110 ${isActive ? "text-white" : "text-gray-400 group-hover:text-primary"}`} />
                  <span>{item.name}</span>
                </div>
                {item.badge && item.badge > 0 && !isActive && (
                  <span className="bg-accent-pink text-white text-[10px] px-1.5 py-0.5 rounded-full font-bold">
                    {item.badge}
                  </span>
                )}
              </Link>
            );
          })}
        </nav>
      </div>

      {/* User Info / Logout */}
      {currentUser ? (
        <div className="border-t border-[var(--card-border)] pt-4 flex flex-col gap-3">
          <div className="flex items-center gap-3 px-2">
            <img
              src={`/uploads/avatars/${currentUser.avatarFilename}`}
              alt={currentUser.fullName}
              className="w-10 h-10 rounded-xl object-cover ring-2 ring-primary/20"
              onError={(e) => {
                e.currentTarget.src = "/assets/images/icons/icon-192x192.png";
              }}
            />
            <div className="overflow-hidden">
              <p className="font-semibold text-sm truncate">{currentUser.fullName}</p>
              <p className="text-[11px] text-gray-400 truncate">@{currentUser.username}</p>
            </div>
          </div>
          <button
            onClick={handleLogout}
            className="flex items-center gap-3.5 w-full px-4 py-2.5 rounded-xl font-medium text-sm text-accent-pink hover:bg-red-50 dark:hover:bg-red-950/20 transition-all duration-200 group"
          >
            <LogOut className="w-5 h-5 transition-transform duration-200 group-hover:translate-x-0.5" />
            <span>Đăng xuất</span>
          </button>
        </div>
      ) : (
        <div className="flex flex-col gap-2">
          <Link
            href="/login"
            className="flex items-center justify-center w-full px-4 py-2.5 rounded-xl font-semibold text-sm bg-primary text-white hover:bg-primary-hover shadow-premium transition-all text-center"
          >
            Đăng nhập
          </Link>
        </div>
      )}
    </aside>
  );
}
