"use client";

import { useState, useEffect } from "react";
import { Search, Sun, Moon, Sparkles, User, FileText, ChevronDown, Menu } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";

interface PageIdentity {
  id: number;
  pageName: string;
  pageUsername: string;
  avatarFilename: string;
  isVerified?: boolean;
}

interface HeaderProps {
  currentIdentity?: {
    type: string;
    id: number;
    name: string;
    avatar: string;
    username: string;
    verificationType?: string | null;
  } | null;
  myPages?: PageIdentity[];
}

export default function Header({ currentIdentity, myPages = [] }: HeaderProps) {
  const [theme, setTheme] = useState<"light" | "dark">("light");
  const [showIdentityMenu, setShowIdentityMenu] = useState(false);
  const router = useRouter();

  // Khởi tạo theme
  useEffect(() => {
    const isDark = document.documentElement.classList.contains("dark");
    setTheme(isDark ? "dark" : "light");
  }, []);

  const toggleTheme = () => {
    if (theme === "light") {
      document.documentElement.classList.add("dark");
      setTheme("dark");
      localStorage.setItem("theme", "dark");
    } else {
      document.documentElement.classList.remove("dark");
      setTheme("light");
      localStorage.setItem("theme", "light");
    }
  };

  // Thay đổi danh tính hoạt động (User <=> Page)
  const handleSwitchIdentity = async (type: "user" | "page", id: number) => {
    setShowIdentityMenu(false);
    
    // Lưu identity mới vào cookie bằng cách gọi API hoặc trực tiếp set document.cookie
    const identityValue = JSON.stringify({ type, id });
    document.cookie = `frest_identity=${encodeURIComponent(identityValue)}; path=/; max-age=2592000`; // 30 ngày
    
    router.refresh();
  };

  return (
    <header className="sticky top-0 w-full bg-[var(--card-bg)]/80 backdrop-blur-md border-b border-[var(--card-border)] py-3 px-6 flex items-center justify-between z-30">
      {/* Search Input */}
      <div className="relative w-full max-w-md hidden sm:block">
        <span className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
          <Search className="h-4.5 w-4.5 text-gray-400" />
        </span>
        <input
          type="text"
          placeholder="Tìm kiếm bài viết, hashtag, bạn bè..."
          className="w-full pl-10 pr-4 py-2 text-sm bg-gray-100 dark:bg-[#202024] border border-transparent rounded-xl focus:outline-none focus:bg-white dark:focus:bg-[#18181c] focus:border-primary/30 transition-all"
        />
      </div>

      {/* Brand Logo for Mobile */}
      <Link href="/" className="flex items-center gap-2 sm:hidden">
        <div className="w-8 h-8 rounded-lg bg-gradient-to-tr from-primary to-accent-purple flex items-center justify-center">
          <Sparkles className="w-4 h-4 text-white" />
        </div>
        <span className="font-bold text-lg text-primary">Frest</span>
      </Link>

      {/* Actions (Identity switcher, Theme toggle) */}
      <div className="flex items-center gap-4">
        {/* Toggle Theme */}
        <button
          onClick={toggleTheme}
          className="p-2 rounded-xl bg-gray-100 dark:bg-[#202024] hover:bg-gray-200 dark:hover:bg-[#2a2a30] transition-colors"
          title="Đổi giao diện"
        >
          {theme === "light" ? <Moon className="w-4.5 h-4.5" /> : <Sun className="w-4.5 h-4.5 text-yellow-400" />}
        </button>

        {/* Switch Identity Dropdown */}
        {currentIdentity && (
          <div className="relative">
            <button
              onClick={() => setShowIdentityMenu(!showIdentityMenu)}
              className="flex items-center gap-2 px-3 py-1.5 rounded-xl border border-[var(--card-border)] bg-gray-50 dark:bg-[#1e1e22] hover:bg-gray-100 dark:hover:bg-[#28282e] transition-colors"
            >
              <img
                src={
                  currentIdentity.type === "page"
                    ? `/uploads/avatars/${currentIdentity.avatar}`
                    : `/uploads/avatars/${currentIdentity.avatar}`
                }
                alt={currentIdentity.name}
                className="w-6 h-6 rounded-lg object-cover"
                onError={(e) => {
                  e.currentTarget.src = "/assets/images/icons/icon-192x192.png";
                }}
              />
              <div className="text-left hidden md:block max-w-[120px]">
                <p className="text-xs font-semibold truncate leading-none mb-0.5 flex items-center gap-0.5">
                  <span>{currentIdentity.name}</span>
                  {currentIdentity.verificationType === "official" && (
                    <svg className="w-3.5 h-3.5 inline-block shrink-0" viewBox="0 0 24 24" title="Đã xác minh">
                      <g fillRule="evenodd" transform="translate(-92)">
                        <path fill="#1877f2" d="m115.887 14.475-1.269-2.475 1.267-2.474a1.02 1.02 0 0 0-.355-1.326l-2.334-1.51-.14-2.775a1.018 1.018 0 0 0-.97-.971l-2.778-.14-1.51-2.336a1.02 1.02 0 0 0-1.324-.354L104 1.38 101.526.114a1.02 1.02 0 0 0-1.326.354l-1.509 2.336-2.777.14a1.017 1.017 0 0 0-.97.97l-.14 2.777L92.468 8.2a1.02 1.02 0 0 0-.354 1.325L93.382 12l-1.268 2.474a1.02 1.02 0 0 0 .355 1.326l2.335 1.509.14 2.776c.025.528.443.945.97.971l2.777.14 1.51 2.336a1.02 1.02 0 0 0 1.324.354L104 22.62l2.474 1.267c.469.242 1.039.09 1.326-.355l1.51-2.335 2.776-.14c.527-.026.945-.443.97-.97l.14-2.777 2.336-1.51c.443-.286.595-.856.354-1.324" />
                        <path fill="#ffffff" d="m109.207 9.707-6.5 6.5a.996.996 0 0 1-1.414 0l-3-3a1 1 0 1 1 1.414-1.414L102 14.086l5.793-5.793a1 1 0 1 1 1.414 1.414" />
                      </g>
                    </svg>
                  )}
                </p>
                <p className="text-[10px] text-gray-400 font-medium uppercase tracking-wider leading-none">
                  {currentIdentity.type === "page" ? "Trang" : "Cá nhân"}
                </p>
              </div>
              <ChevronDown className="w-3.5 h-3.5 text-gray-400" />
            </button>

            {showIdentityMenu && (
              <div className="absolute right-0 mt-2 w-56 rounded-xl bg-[var(--card-bg)] border border-[var(--card-border)] shadow-premium p-1.5 animate-in fade-in slide-in-from-top-2 duration-150 z-50">
                <p className="text-[10px] font-bold text-gray-400 px-3.5 py-1.5 uppercase tracking-wider">
                  Chuyển vai trò hoạt động
                </p>
                
                {/* Switch back to User */}
                <button
                  onClick={() => handleSwitchIdentity("user", currentIdentity.id)}
                  className={`w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-left text-xs font-semibold hover:bg-gray-100 dark:hover:bg-[#202024] transition-colors ${
                    currentIdentity.type === "user" ? "text-primary bg-primary/5" : "text-gray-600 dark:text-gray-300"
                  }`}
                >
                  <User className="w-4 h-4 text-gray-400" />
                  <span>Cá nhân (Chính)</span>
                </button>

                {/* Switch to Pages owned */}
                {myPages.length > 0 && (
                  <div className="border-t border-[var(--card-border)] my-1.5">
                    <p className="text-[10px] font-bold text-gray-400 px-3.5 py-1.5 uppercase tracking-wider">
                      Trang của bạn
                    </p>
                    {myPages.map((pg) => (
                      <button
                        key={pg.id}
                        onClick={() => handleSwitchIdentity("page", pg.id)}
                        className={`w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-left text-xs font-semibold hover:bg-gray-100 dark:hover:bg-[#202024] transition-colors ${
                          currentIdentity.type === "page" && currentIdentity.id === pg.id
                            ? "text-primary bg-primary/5"
                            : "text-gray-600 dark:text-gray-300"
                        }`}
                      >
                        <FileText className="w-4 h-4 text-gray-400" />
                        <span className="truncate flex items-center gap-1">
                          <span>{pg.pageName}</span>
                          {pg.isVerified && (
                            <svg className="w-3.5 h-3.5 inline-block shrink-0" viewBox="0 0 24 24" title="Trang chính thức">
                              <g fillRule="evenodd" transform="translate(-92)">
                                <path fill="#1877f2" d="m115.887 14.475-1.269-2.475 1.267-2.474a1.02 1.02 0 0 0-.355-1.326l-2.334-1.51-.14-2.775a1.018 1.018 0 0 0-.97-.971l-2.778-.14-1.51-2.336a1.02 1.02 0 0 0-1.324-.354L104 1.38 101.526.114a1.02 1.02 0 0 0-1.326.354l-1.509 2.336-2.777.14a1.017 1.017 0 0 0-.97.97l-.14 2.777L92.468 8.2a1.02 1.02 0 0 0-.354 1.325L93.382 12l-1.268 2.474a1.02 1.02 0 0 0 .355 1.326l2.335 1.509.14 2.776c.025.528.443.945.97.971l2.777.14 1.51 2.336a1.02 1.02 0 0 0 1.324.354L104 22.62l2.474 1.267c.469.242 1.039.09 1.326-.355l1.51-2.335 2.776-.14c.527-.026.945-.443.97-.97l.14-2.777 2.336-1.51c.443-.286.595-.856.354-1.324" />
                                <path fill="#ffffff" d="m109.207 9.707-6.5 6.5a.996.996 0 0 1-1.414 0l-3-3a1 1 0 1 1 1.414-1.414L102 14.086l5.793-5.793a1 1 0 1 1 1.414 1.414" />
                              </g>
                            </svg>
                          )}
                        </span>
                      </button>
                    ))}
                  </div>
                )}
              </div>
            )}
          </div>
        )}
      </div>
    </header>
  );
}
