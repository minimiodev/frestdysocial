-- ============================================================
-- FREST SOCIAL NETWORK - DATABASE SCHEMA
-- Paste toàn bộ file này vào Supabase SQL Editor và nhấn Run
-- URL: https://supabase.com/dashboard/project/rokmiiisjuowipexrxws/sql/new
-- ============================================================

-- CreateTable
CREATE TABLE "users" (
    "id" SERIAL NOT NULL,
    "username" VARCHAR(100) NOT NULL,
    "full_name" VARCHAR(100) NOT NULL DEFAULT '',
    "first_name" VARCHAR(50) NOT NULL DEFAULT '',
    "middle_name" VARCHAR(50) NOT NULL DEFAULT '',
    "last_name" VARCHAR(50) NOT NULL DEFAULT '',
    "name_display_order" VARCHAR(30) NOT NULL DEFAULT 'last_middle_first',
    "display_name_last_updated" TIMESTAMP(3),
    "username_last_updated" TIMESTAMP(3),
    "pending_first_name" VARCHAR(50),
    "pending_middle_name" VARCHAR(50),
    "pending_last_name" VARCHAR(50),
    "pending_name_display_order" VARCHAR(30),
    "name_change_status" VARCHAR(20) NOT NULL DEFAULT 'none',
    "password_hash" VARCHAR(255) NOT NULL,
    "email" VARCHAR(255) NOT NULL,
    "avatar_filename" VARCHAR(255) NOT NULL DEFAULT 'avatar_default.png',
    "bio" VARCHAR(255) NOT NULL DEFAULT '',
    "is_private" BOOLEAN NOT NULL DEFAULT false,
    "show_nsfw" BOOLEAN NOT NULL DEFAULT false,
    "is_adult" BOOLEAN NOT NULL DEFAULT false,
    "dob" DATE,
    "id_proof_filename" VARCHAR(255),
    "age_verification_status" VARCHAR(30) NOT NULL DEFAULT 'unverified',
    "reset_token" VARCHAR(255),
    "reset_token_expires" TIMESTAMP(3),
    "activity_dismissed_at" TIMESTAMP(3),
    "qr_reset_at" TIMESTAMP(3),
    "verification_type" VARCHAR(30),
    "phone_number" VARCHAR(30),
    "show_email" BOOLEAN NOT NULL DEFAULT true,
    "show_phone" BOOLEAN NOT NULL DEFAULT true,
    "show_gender" BOOLEAN NOT NULL DEFAULT true,
    "show_workplace" BOOLEAN NOT NULL DEFAULT true,
    "show_lives_at" BOOLEAN NOT NULL DEFAULT true,
    "show_country" BOOLEAN NOT NULL DEFAULT true,
    "show_dob" BOOLEAN NOT NULL DEFAULT true,
    "phone_verified" BOOLEAN NOT NULL DEFAULT true,
    "phone_verification_code" VARCHAR(10),
    "gender" VARCHAR(30),
    "workplace" VARCHAR(255),
    "lives_at" VARCHAR(255),
    "country" VARCHAR(100),
    "status" VARCHAR(30) NOT NULL DEFAULT 'active',
    "status_reason" TEXT,
    "lock_until" TIMESTAMP(3),
    "last_active" TIMESTAMP(3),
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "is_page" BOOLEAN NOT NULL DEFAULT false,
    "page_category" VARCHAR(100),
    "sync_transparency_status" BOOLEAN NOT NULL DEFAULT true,
    CONSTRAINT "users_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "pages" (
    "id" SERIAL NOT NULL,
    "owner_id" INTEGER NOT NULL,
    "page_name" VARCHAR(100) NOT NULL,
    "page_username" VARCHAR(100) NOT NULL,
    "avatar_filename" VARCHAR(255) NOT NULL DEFAULT 'avatar_default.png',
    "cover_filename" VARCHAR(255),
    "bio" VARCHAR(255) NOT NULL DEFAULT '',
    "category" VARCHAR(100) NOT NULL DEFAULT 'Cộng đồng',
    "is_verified" BOOLEAN NOT NULL DEFAULT false,
    "verification_type" VARCHAR(30),
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "pages_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "page_follows" (
    "user_id" INTEGER NOT NULL,
    "page_id" INTEGER NOT NULL,
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "page_follows_pkey" PRIMARY KEY ("user_id","page_id")
);

-- CreateTable
CREATE TABLE "posts" (
    "id" SERIAL NOT NULL,
    "user_id" INTEGER NOT NULL,
    "content" TEXT NOT NULL,
    "image_filename" TEXT,
    "video_filename" VARCHAR(255),
    "audio_filename" VARCHAR(255),
    "document_filename" VARCHAR(255),
    "software_filename" VARCHAR(255),
    "allow_download" BOOLEAN NOT NULL DEFAULT true,
    "is_copyright_violation" BOOLEAN NOT NULL DEFAULT false,
    "copyright_owner" VARCHAR(255),
    "copyright_details" TEXT,
    "is_nsfw" BOOLEAN NOT NULL DEFAULT false,
    "repost_of_post_id" INTEGER,
    "page_id" INTEGER,
    "link_preview_url" VARCHAR(2048),
    "link_preview_title" VARCHAR(512),
    "link_preview_desc" VARCHAR(1024),
    "link_preview_image" VARCHAR(2048),
    "is_pinned" BOOLEAN NOT NULL DEFAULT false,
    "post_token" VARCHAR(32),
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "posts_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "replies" (
    "id" SERIAL NOT NULL,
    "post_id" INTEGER NOT NULL,
    "user_id" INTEGER NOT NULL,
    "content" TEXT NOT NULL,
    "parent_reply_id" INTEGER,
    "updated_at" TIMESTAMP(3),
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "replies_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "reactions" (
    "id" SERIAL NOT NULL,
    "user_id" INTEGER NOT NULL,
    "post_id" INTEGER,
    "reply_id" INTEGER,
    "reaction_type" VARCHAR(20) NOT NULL DEFAULT 'like',
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "reactions_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "follows" (
    "follower_id" INTEGER NOT NULL,
    "followed_id" INTEGER NOT NULL,
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "follows_pkey" PRIMARY KEY ("follower_id","followed_id")
);

-- CreateTable
CREATE TABLE "admins" (
    "id" SERIAL NOT NULL,
    "username" VARCHAR(100) NOT NULL,
    "password_hash" VARCHAR(255) NOT NULL,
    "email" VARCHAR(255),
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "admins_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "settings" (
    "key_name" VARCHAR(50) NOT NULL,
    "key_value" TEXT,
    CONSTRAINT "settings_pkey" PRIMARY KEY ("key_name")
);

-- CreateTable
CREATE TABLE "copyright_complaints" (
    "id" SERIAL NOT NULL,
    "reporter_name" VARCHAR(100) NOT NULL,
    "reporter_email" VARCHAR(100) NOT NULL,
    "reporter_phone" VARCHAR(30),
    "post_url" VARCHAR(2048) NOT NULL,
    "description" TEXT NOT NULL,
    "evidence_filename" VARCHAR(255),
    "status" VARCHAR(30) NOT NULL DEFAULT 'pending',
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "copyright_complaints_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "messages" (
    "id" SERIAL NOT NULL,
    "sender_type" VARCHAR(20) NOT NULL DEFAULT 'user',
    "sender_id" INTEGER NOT NULL,
    "receiver_type" VARCHAR(20) NOT NULL,
    "receiver_id" INTEGER NOT NULL,
    "message_text" TEXT,
    "media_filename" VARCHAR(255),
    "media_type" VARCHAR(50),
    "is_read" BOOLEAN NOT NULL DEFAULT false,
    "is_recalled" BOOLEAN NOT NULL DEFAULT false,
    "is_edited" BOOLEAN NOT NULL DEFAULT false,
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "messages_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "message_reactions" (
    "id" SERIAL NOT NULL,
    "message_id" INTEGER NOT NULL,
    "reactor_type" VARCHAR(20) NOT NULL DEFAULT 'user',
    "reactor_id" INTEGER NOT NULL,
    "reaction_emoji" VARCHAR(10) NOT NULL,
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "message_reactions_pkey" PRIMARY KEY ("message_id","reactor_type","reactor_id")
);

-- CreateTable
CREATE TABLE "chat_groups" (
    "id" SERIAL NOT NULL,
    "name" VARCHAR(150) NOT NULL,
    "avatar_filename" VARCHAR(255) NOT NULL DEFAULT 'group_default.png',
    "description" TEXT,
    "creator_type" VARCHAR(20) NOT NULL,
    "creator_id" INTEGER NOT NULL,
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "chat_groups_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "chat_group_members" (
    "group_id" INTEGER NOT NULL,
    "member_type" VARCHAR(20) NOT NULL,
    "member_id" INTEGER NOT NULL,
    "role" VARCHAR(30) NOT NULL DEFAULT 'member',
    "joined_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "chat_group_members_pkey" PRIMARY KEY ("group_id","member_type","member_id")
);

-- CreateTable
CREATE TABLE "polls" (
    "id" SERIAL NOT NULL,
    "post_id" INTEGER NOT NULL,
    "question" VARCHAR(255) NOT NULL,
    "expires_at" TIMESTAMP(3),
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "polls_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "poll_options" (
    "id" SERIAL NOT NULL,
    "poll_id" INTEGER NOT NULL,
    "option_text" VARCHAR(150) NOT NULL,
    CONSTRAINT "poll_options_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "poll_votes" (
    "id" SERIAL NOT NULL,
    "poll_id" INTEGER NOT NULL,
    "option_id" INTEGER NOT NULL,
    "user_id" INTEGER NOT NULL,
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "poll_votes_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "bookmarks" (
    "id" SERIAL NOT NULL,
    "user_id" INTEGER NOT NULL,
    "post_id" INTEGER NOT NULL,
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "bookmarks_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "hashtags" (
    "id" SERIAL NOT NULL,
    "tag" VARCHAR(100) NOT NULL,
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "hashtags_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "post_hashtags" (
    "post_id" INTEGER NOT NULL,
    "hashtag_id" INTEGER NOT NULL,
    CONSTRAINT "post_hashtags_pkey" PRIMARY KEY ("post_id","hashtag_id")
);

-- CreateTable
CREATE TABLE "wiki_moods" (
    "id" SERIAL NOT NULL,
    "user_id" INTEGER NOT NULL,
    "content" VARCHAR(200) NOT NULL,
    "emoji" VARCHAR(30) NOT NULL,
    "color" VARCHAR(150) NOT NULL,
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "expires_at" TIMESTAMP(3),
    CONSTRAINT "wiki_moods_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "stories" (
    "id" SERIAL NOT NULL,
    "user_id" INTEGER NOT NULL,
    "media_filename" VARCHAR(255) NOT NULL,
    "media_type" VARCHAR(50) NOT NULL,
    "caption" VARCHAR(255),
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "stories_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "story_views" (
    "id" SERIAL NOT NULL,
    "story_id" INTEGER NOT NULL,
    "viewer_id" INTEGER NOT NULL,
    "viewed_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "story_views_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "story_reactions" (
    "id" SERIAL NOT NULL,
    "story_id" INTEGER NOT NULL,
    "user_id" INTEGER NOT NULL,
    "reaction_type" VARCHAR(20) NOT NULL,
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "story_reactions_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "blocks" (
    "blocker_id" INTEGER NOT NULL,
    "blocked_id" INTEGER NOT NULL,
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "blocks_pkey" PRIMARY KEY ("blocker_id","blocked_id")
);

-- CreateTable
CREATE TABLE "reports" (
    "id" SERIAL NOT NULL,
    "reporter_id" INTEGER NOT NULL,
    "reported_user_id" INTEGER,
    "reported_post_id" INTEGER,
    "reported_reply_id" INTEGER,
    "reason" VARCHAR(255) NOT NULL,
    "details" TEXT,
    "status" VARCHAR(30) NOT NULL DEFAULT 'pending',
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "reports_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "name_history" (
    "id" SERIAL NOT NULL,
    "user_id" INTEGER NOT NULL,
    "old_name" VARCHAR(255) NOT NULL,
    "new_name" VARCHAR(255) NOT NULL,
    "changed_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "name_history_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "login_history" (
    "id" SERIAL NOT NULL,
    "user_id" INTEGER NOT NULL,
    "ip_address" VARCHAR(45) NOT NULL,
    "user_agent" VARCHAR(255),
    "login_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "login_history_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "notifications" (
    "id" SERIAL NOT NULL,
    "user_id" INTEGER NOT NULL,
    "sender_id" INTEGER,
    "type" VARCHAR(50) NOT NULL,
    "target_id" INTEGER,
    "is_read" BOOLEAN NOT NULL DEFAULT false,
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "notifications_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE UNIQUE INDEX "users_username_key" ON "users"("username");
CREATE UNIQUE INDEX "users_email_key" ON "users"("email");
CREATE UNIQUE INDEX "pages_page_username_key" ON "pages"("page_username");
CREATE UNIQUE INDEX "posts_post_token_key" ON "posts"("post_token");
CREATE UNIQUE INDEX "admins_username_key" ON "admins"("username");
CREATE UNIQUE INDEX "poll_votes_poll_id_user_id_key" ON "poll_votes"("poll_id", "user_id");
CREATE UNIQUE INDEX "bookmarks_user_id_post_id_key" ON "bookmarks"("user_id", "post_id");
CREATE UNIQUE INDEX "hashtags_tag_key" ON "hashtags"("tag");

-- AddForeignKey
ALTER TABLE "pages" ADD CONSTRAINT "pages_owner_id_fkey" FOREIGN KEY ("owner_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "page_follows" ADD CONSTRAINT "page_follows_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "page_follows" ADD CONSTRAINT "page_follows_page_id_fkey" FOREIGN KEY ("page_id") REFERENCES "pages"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "posts" ADD CONSTRAINT "posts_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "posts" ADD CONSTRAINT "posts_page_id_fkey" FOREIGN KEY ("page_id") REFERENCES "pages"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "posts" ADD CONSTRAINT "posts_repost_of_post_id_fkey" FOREIGN KEY ("repost_of_post_id") REFERENCES "posts"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "replies" ADD CONSTRAINT "replies_post_id_fkey" FOREIGN KEY ("post_id") REFERENCES "posts"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "replies" ADD CONSTRAINT "replies_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "replies" ADD CONSTRAINT "replies_parent_reply_id_fkey" FOREIGN KEY ("parent_reply_id") REFERENCES "replies"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "reactions" ADD CONSTRAINT "reactions_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "reactions" ADD CONSTRAINT "reactions_post_id_fkey" FOREIGN KEY ("post_id") REFERENCES "posts"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "reactions" ADD CONSTRAINT "reactions_reply_id_fkey" FOREIGN KEY ("reply_id") REFERENCES "replies"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "follows" ADD CONSTRAINT "follows_follower_id_fkey" FOREIGN KEY ("follower_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "follows" ADD CONSTRAINT "follows_followed_id_fkey" FOREIGN KEY ("followed_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "messages" ADD CONSTRAINT "messages_sender_id_fkey" FOREIGN KEY ("sender_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "message_reactions" ADD CONSTRAINT "message_reactions_message_id_fkey" FOREIGN KEY ("message_id") REFERENCES "messages"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "chat_groups" ADD CONSTRAINT "chat_groups_creator_id_fkey" FOREIGN KEY ("creator_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "chat_group_members" ADD CONSTRAINT "chat_group_members_group_id_fkey" FOREIGN KEY ("group_id") REFERENCES "chat_groups"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "chat_group_members" ADD CONSTRAINT "chat_group_members_member_id_fkey" FOREIGN KEY ("member_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "polls" ADD CONSTRAINT "polls_post_id_fkey" FOREIGN KEY ("post_id") REFERENCES "posts"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "poll_options" ADD CONSTRAINT "poll_options_poll_id_fkey" FOREIGN KEY ("poll_id") REFERENCES "polls"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "poll_votes" ADD CONSTRAINT "poll_votes_poll_id_fkey" FOREIGN KEY ("poll_id") REFERENCES "polls"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "poll_votes" ADD CONSTRAINT "poll_votes_option_id_fkey" FOREIGN KEY ("option_id") REFERENCES "poll_options"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "poll_votes" ADD CONSTRAINT "poll_votes_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "bookmarks" ADD CONSTRAINT "bookmarks_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "bookmarks" ADD CONSTRAINT "bookmarks_post_id_fkey" FOREIGN KEY ("post_id") REFERENCES "posts"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "post_hashtags" ADD CONSTRAINT "post_hashtags_post_id_fkey" FOREIGN KEY ("post_id") REFERENCES "posts"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "post_hashtags" ADD CONSTRAINT "post_hashtags_hashtag_id_fkey" FOREIGN KEY ("hashtag_id") REFERENCES "hashtags"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "wiki_moods" ADD CONSTRAINT "wiki_moods_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "stories" ADD CONSTRAINT "stories_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "story_views" ADD CONSTRAINT "story_views_story_id_fkey" FOREIGN KEY ("story_id") REFERENCES "stories"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "story_views" ADD CONSTRAINT "story_views_viewer_id_fkey" FOREIGN KEY ("viewer_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "story_reactions" ADD CONSTRAINT "story_reactions_story_id_fkey" FOREIGN KEY ("story_id") REFERENCES "stories"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "story_reactions" ADD CONSTRAINT "story_reactions_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "blocks" ADD CONSTRAINT "blocks_blocker_id_fkey" FOREIGN KEY ("blocker_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "blocks" ADD CONSTRAINT "blocks_blocked_id_fkey" FOREIGN KEY ("blocked_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "reports" ADD CONSTRAINT "reports_reporter_id_fkey" FOREIGN KEY ("reporter_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "reports" ADD CONSTRAINT "reports_reported_user_id_fkey" FOREIGN KEY ("reported_user_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "reports" ADD CONSTRAINT "reports_reported_post_id_fkey" FOREIGN KEY ("reported_post_id") REFERENCES "posts"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "reports" ADD CONSTRAINT "reports_reported_reply_id_fkey" FOREIGN KEY ("reported_reply_id") REFERENCES "replies"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "name_history" ADD CONSTRAINT "name_history_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "login_history" ADD CONSTRAINT "login_history_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "notifications" ADD CONSTRAINT "notifications_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "notifications" ADD CONSTRAINT "notifications_sender_id_fkey" FOREIGN KEY ("sender_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
