import { createClient } from "microcms-js-sdk";

if (
  !import.meta.env.MICROCMS_SERVICE_DOMAIN ||
  !import.meta.env.MICROCMS_API_KEY
) {
  throw new Error(
    "microCMS の環境変数が設定されていません。.env ファイルを確認してください。\n" +
      "MICROCMS_SERVICE_DOMAIN と MICROCMS_API_KEY を設定してください。",
  );
}

export const client = createClient({
  serviceDomain: import.meta.env.MICROCMS_SERVICE_DOMAIN,
  apiKey: import.meta.env.MICROCMS_API_KEY,
});

// ニュースの型定義
export type News = {
  id: string;
  createdAt: string;
  updatedAt: string;
  publishedAt: string;
  title: string;
  content: string;
  category?: string;
};

// 工事実績の型定義
export type Work = {
  id: string;
  createdAt: string;
  updatedAt: string;
  publishedAt: string;
  title: string;
  image: {
    url: string;
    height: number;
    width: number;
  };
  completionYear?: number;
  description?: string;
};

// 求人情報の型定義
export type JobPosting = {
  id: string;
  createdAt: string;
  updatedAt: string;
  publishedAt: string;
  /** 職種名 */
  title: string;
  /** 雇用形態（例: 正社員、パートタイム） */
  employmentType?: string;
  /** 勤務地 */
  workLocation?: string;
  /** 仕事内容 */
  description?: string;
  /** 応募資格・条件 */
  requirements?: string;
};
