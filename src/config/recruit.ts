/**
 * =============================================================================
 * 採用ページの「募集職種」「応募フォーム」の表示制御は、このファイルだけで行います。
 * =============================================================================
 *
 * 【編集する場所】
 *   下の `recruitConfig` の 2 行だけです。
 *     - openingsMode  … 募集職種ブロック全体
 *     - applyMode     … 応募フォームブロック全体
 *
 * 【よく使う組み合わせ】
 *
 *   募集していない（見出しは出す・フォームは出さない）:
 *     openingsMode: "no-vacancies",
 *     applyMode: "closed-notice",
 *
 *   募集している（microCMS の求人一覧＋応募フォーム）:
 *     openingsMode: "list",
 *     applyMode: "form",
 *
 *   ブロックごとページから消す（ナビの #openings / #apply リンクは要調整）:
 *     openingsMode: "hidden",
 *     applyMode: "hidden",
 *
 * 変更後に画面が変わらないときは `astro dev` を一度止めて再起動してください。
 *
 * 詳細: docs/recruit-openings-and-apply.md
 * =============================================================================
 */

/** microCMS 連携で一覧を出す / 固定で「募集なし」表示 / セクション非表示 */
export type RecruitOpeningsMode = "list" | "no-vacancies" | "hidden";

/** フォーム表示 / 受付停止の案内 / セクション非表示 */
export type RecruitApplyMode = "form" | "closed-notice" | "hidden";

export const recruitConfig = {
  /**
   * 募集職種セクション（採用ページの #openings）
   * - list … microCMS「jobs」から一覧（0件なら「現在募集中の職種はありません。」）
   * - no-vacancies … 上記の「募集なし」文言だけ（API なし）
   * - hidden … セクションごと非表示
   */
  openingsMode: "no-vacancies" as RecruitOpeningsMode,

  /**
   * 応募フォームセクション（採用ページの #apply）
   * - form … フォーム表示
   * - closed-notice … 受付停止の案内文のみ
   * - hidden … セクションごと非表示
   */
  applyMode: "closed-notice" as RecruitApplyMode,
} as const;
