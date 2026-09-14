import express, { Request, Response } from "express";
import path from "path";
import { createServer as createViteServer } from "vite";
import { GoogleGenAI } from "@google/genai";
import crypto from "crypto";

const app = express();
const PORT = 3000;

app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Lazy GoogleGenAI client
let aiClient: GoogleGenAI | null = null;
function getAi(): GoogleGenAI {
  if (!aiClient) {
    aiClient = new GoogleGenAI({
      apiKey: process.env.GEMINI_API_KEY || "",
      httpOptions: {
        headers: {
          "User-Agent": "aistudio-build",
        },
      },
    });
  }
  return aiClient;
}

// ----------------------------------------------------
// In-Memory Database Store with full relational schema
// ----------------------------------------------------
interface Store {
  sites: any[];
  categories: any[];
  trendCandidates: any[];
  articles: any[];
  articleSources: any[];
  imageGroups: any[];
  images: any[];
  imageKeywords: any[];
  votes: any[];
  comments: any[];
  bannedKeywords: any[];
  snsQueue: any[];
  siteSettings: Record<number, any>;
  logs: any[];
}

const store: Store = {
  sites: [
    {
      id: 1,
      subdomain: "",
      name: "しらんけど",
      description: "「いま日本で何が話題か」を自動分析するトレンドサイト。しらんけど。",
      genre: "general",
      logoUrl: "",
      isPublic: true,
      allowAutoPublish: true,
      youtubeThumbnailEnabled: true,
    },
    {
      id: 2,
      subdomain: "game",
      name: "しらんけど ゲーム速報",
      description: "Steam・新作ゲーム・大型アプデのトレンドを独自集計。しらんけど。",
      genre: "game",
      logoUrl: "",
      isPublic: true,
      allowAutoPublish: true,
      youtubeThumbnailEnabled: true,
    },
    {
      id: 3,
      subdomain: "entame",
      name: "しらんけど エンタメ",
      description: "お笑い・バラエティ・芸能の一次ソース付きトレンド速報。しらんけど。",
      genre: "entertainment",
      logoUrl: "",
      isPublic: true,
      allowAutoPublish: true,
      youtubeThumbnailEnabled: true,
    },
    {
      id: 4,
      subdomain: "youtube",
      name: "しらんけど YouTube",
      description: "YouTube急上昇＆注目クリエイターの話題度チェック。しらんけど。",
      genre: "youtube",
      logoUrl: "",
      isPublic: true,
      allowAutoPublish: true,
      youtubeThumbnailEnabled: true,
    },
    {
      id: 5,
      subdomain: "news",
      name: "しらんけど ニュース",
      description: "読まれているニュースと検索トレンドの交差分析。しらんけど。",
      genre: "news",
      logoUrl: "",
      isPublic: true,
      allowAutoPublish: false, // ニュースは慎重に確認待ち
      youtubeThumbnailEnabled: false,
    },
  ],
  categories: [
    { id: 1, siteId: 1, slug: "all", name: "総合", sortOrder: 1 },
    { id: 2, siteId: 1, slug: "entertainment", name: "エンタメ", sortOrder: 2 },
    { id: 3, siteId: 1, slug: "game", name: "ゲーム", sortOrder: 3 },
    { id: 4, siteId: 1, slug: "tech", name: "テクノロジー", sortOrder: 4 },
    { id: 5, siteId: 1, slug: "social", name: "SNS・ネット話題", sortOrder: 5 },
    { id: 6, siteId: 2, slug: "steam", name: "Steam/PC", sortOrder: 1 },
    { id: 7, siteId: 2, slug: "console", name: "PS5/Switch", sortOrder: 2 },
    { id: 8, siteId: 2, slug: "app", name: "スマホアプリ", sortOrder: 3 },
  ],
  trendCandidates: [
    {
      id: 1,
      siteId: 1,
      normalizedKeyword: "千鳥大悟新作番組",
      displayKeyword: "千鳥 大悟 新作番組 ネット独占配信決定",
      sources: ["yahoo", "news", "youtube"],
      googleScore: 82,
      yahooScore: 94,
      youtubeScore: 85,
      newsScore: 88,
      gameScore: 10,
      shirankedoIndex: 88,
      isRapidRise: true,
      growthRate: 155.0,
      firstDetectedAt: new Date(Date.now() - 2 * 3600 * 1000).toISOString(),
      lastUpdatedAt: new Date().toISOString(),
      status: "completed",
    },
    {
      id: 2,
      siteId: 1,
      normalizedKeyword: "monsterhunterwildsアップデート",
      displayKeyword: "Monster Hunter Wilds 大型アップデート第1弾告知",
      sources: ["game", "youtube", "google"],
      googleScore: 92,
      yahooScore: 78,
      youtubeScore: 96,
      newsScore: 75,
      gameScore: 100,
      shirankedoIndex: 94,
      isRapidRise: true,
      growthRate: 210.0,
      firstDetectedAt: new Date(Date.now() - 4 * 3600 * 1000).toISOString(),
      lastUpdatedAt: new Date().toISOString(),
      status: "completed",
    },
    {
      id: 3,
      siteId: 1,
      normalizedKeyword: "スタジオジブリ企画展チケット",
      displayKeyword: "スタジオジブリ最新企画展 チケット即日完売",
      sources: ["news", "yahoo", "google"],
      googleScore: 85,
      yahooScore: 80,
      youtubeScore: 60,
      newsScore: 90,
      gameScore: 0,
      shirankedoIndex: 78,
      isRapidRise: false,
      growthRate: 45.0,
      firstDetectedAt: new Date(Date.now() - 72 * 3600 * 1000).toISOString(),
      lastUpdatedAt: new Date().toISOString(),
      status: "completed",
    },
    {
      id: 4,
      siteId: 1,
      normalizedKeyword: "某容疑者sns特定騒動",
      displayKeyword: "事件の某容疑者に関するSNS上の個人特定デマ騒動",
      sources: ["yahoo"],
      googleScore: 40,
      yahooScore: 89,
      youtubeScore: 20,
      newsScore: 10,
      gameScore: 0,
      shirankedoIndex: 52,
      isRapidRise: true,
      growthRate: 130.0,
      firstDetectedAt: new Date(Date.now() - 1 * 3600 * 1000).toISOString(),
      lastUpdatedAt: new Date().toISOString(),
      status: "completed",
    },
    {
      id: 5,
      siteId: 1,
      normalizedKeyword: "新世代オープンソースaiモデル",
      displayKeyword: "新世代オープンソースAIモデルの日本語性能が話題に",
      sources: ["google", "news", "youtube"],
      googleScore: 75,
      yahooScore: 70,
      youtubeScore: 80,
      newsScore: 82,
      gameScore: 20,
      shirankedoIndex: 72,
      isRapidRise: false,
      growthRate: 35.0,
      firstDetectedAt: new Date(Date.now() - 18 * 3600 * 1000).toISOString(),
      lastUpdatedAt: new Date().toISOString(),
      status: "candidate",
    },
  ],
  articles: [
    {
      id: 1,
      siteId: 1,
      categoryId: 2,
      categoryName: "エンタメ",
      title: "千鳥・大悟の新バラエティが独占配信へ 公式発表にSNS歓喜",
      slug: "trend-chidori-daigo-new-show",
      whyTrending: "大手配信プラットフォームが千鳥・大悟の単独MCによるオリジナル新番組を発表。公式PV公開と同時にXやYahoo!リアルタイムで急上昇。",
      body: "大手動画配信サービスは14日、お笑いコンビ「千鳥」の大悟が単独で司会を務める完全新作バラエティ番組の制作・独占配信を発表しました。\n\n公式発表資料およびティザー映像によると、本作は台本なしの即興シチュエーションコメディを主軸とし、豪華ゲスト陣が多数出演する大型企画となっています。千鳥としてのレギュラー番組とはまた異なる大悟独自の世界観が展開されるとあり、お笑いファンを中心に期待の声が急速に広がっています。\n\n制作関係者向けの発表会では、地上波では実現しにくかった挑戦的な企画が盛り込まれることが明言されました。",
      conclusionSentence: "今後の追加出演者や配信開始日の発表次第では、さらにネット上がざわつくことになりそうです。しらんけど。",
      shirankedoIndex: 88,
      indexLabel: "めっちゃ話題",
      isRapidRise: true,
      growthRate: 155.0,
      firstDetectedAt: new Date(Date.now() - 2 * 3600 * 1000).toISOString(),
      imageUrl: "https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=1000&q=80",
      youtubeVideoId: "dQw4w9WgXcQ",
      status: "published",
      isDangerous: false,
      publishedAt: new Date(Date.now() - 1.5 * 3600 * 1000).toISOString(),
      votes: { knew: 41, didntKnow: 72, grow: 85, end: 28 },
    },
    {
      id: 2,
      siteId: 1,
      categoryId: 3,
      categoryName: "ゲーム",
      title: "Monster Hunter Wilds 無料大型アプデ第1弾の詳細公開 新モンスター解禁",
      slug: "trend-mhw-wilds-update-1",
      whyTrending: "カプコン公式生放送で大型タイトルアップデート第1弾の配信日と追加モンスターが正式発表。SteamおよびSNSで爆発的な反響を記録。",
      body: "株式会社カプコンは、全世界で大ヒットを記録しているハンティングアクション最新作『Monster Hunter Wilds』の無料大型タイトルアップデート第1弾に関する公式ロードマップを公開しました。\n\n配信番組内の発表によると、新たな歴戦の古龍種モンスター1体と、過去作から復活を果たす人気モンスターが実装されます。また、武器バランスの調整や追加エンドコンテンツ、新防具シリーズの生産機能も同時解禁されることが確定しました。\n\n公式発表直後から国内外のゲームコミュニティやYouTubeライブ配信では装備ビルドの考察が白熱しています。",
      conclusionSentence: "アップデート当日は狩猟解禁に合わせて有休を申請するハンターが続出する見通しです。しらんけど。",
      shirankedoIndex: 94,
      indexLabel: "めっちゃ話題",
      isRapidRise: true,
      growthRate: 210.0,
      firstDetectedAt: new Date(Date.now() - 4 * 3600 * 1000).toISOString(),
      imageUrl: "https://images.unsplash.com/photo-1538481199705-c710c4e965fc?auto=format&fit=crop&w=1000&q=80",
      youtubeVideoId: "M7lc1UVf-VE",
      status: "published",
      isDangerous: false,
      publishedAt: new Date(Date.now() - 3.5 * 3600 * 1000).toISOString(),
      votes: { knew: 110, didntKnow: 35, grow: 124, end: 21 },
    },
    {
      id: 3,
      siteId: 1,
      categoryId: 2,
      categoryName: "エンタメ",
      title: "スタジオジブリ特別企画展 前売りチケットが開始3分で即完売の盛況",
      slug: "trend-ghibli-exhibition-tickets",
      whyTrending: "今夏開催されるスタジオジブリの回顧企画展の一般チケット販売が開始され、販売サイトへのアクセスが集中し即日完売。",
      body: "都内美術館で開催予定のスタジオジブリ特別企画展の前売りチケット販売が本日午前10時に開始され、わずか数分で全日程の予定枚数が終了しました。\n\n本展覧会では、貴重な手描き背景画や未公開の設定資料、実物大の造形展示などが予定されており、国内のみならず海外ファンからも高い注目を集めていました。\n\n主催者側は公式サイトにて、転売チケットへの注意喚起を行うとともに、追加日程の調整について検討中である旨のアナウンスを行っています。",
      conclusionSentence: "プレミアム価格をつけた悪質な転売にはくれぐれもご注意ください。しらんけど。",
      shirankedoIndex: 78,
      indexLabel: "かなり話題",
      isRapidRise: false,
      growthRate: 45.0,
      firstDetectedAt: new Date(Date.now() - 72 * 3600 * 1000).toISOString(),
      imageUrl: "https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?auto=format&fit=crop&w=1000&q=80",
      status: "published",
      isDangerous: false,
      publishedAt: new Date(Date.now() - 70 * 3600 * 1000).toISOString(),
      votes: { knew: 55, didntKnow: 68, grow: 42, end: 81 },
    },
    {
      id: 4,
      siteId: 1,
      categoryId: 5,
      categoryName: "SNS・ネット話題",
      title: "【保留記事】事件の某容疑者に関するSNS上の個人特定デマ騒動",
      slug: "trend-held-rumor-case",
      whyTrending: "SNS上で無関係の一般人の氏名や勤務先が容疑者として拡散。危険ジャンル検知により自動保留。",
      body: "ネット上の匿名掲示板およびSNSにおいて、事件の容疑者であるかのように装った一般人の個人情報が拡散されています。警察発表および大手報道機関による公式裏付けは一切確認されておらず、明らかなデマである可能性が極めて高いため、当サイトでは安全ブレーキが作動しました。",
      conclusionSentence: "未確認の噂を軽はずみに拡散すると法的責任を問われる可能性があります。しらんけど。",
      shirankedoIndex: 52,
      indexLabel: "話題",
      isRapidRise: true,
      growthRate: 130.0,
      firstDetectedAt: new Date(Date.now() - 1 * 3600 * 1000).toISOString(),
      imageUrl: "https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=1000&q=80",
      status: "on_hold",
      isDangerous: true,
      dangerReason: "危険キーワード検知: 容疑, 特定 / 一次ソース不足 (SNS噂のみ)",
      publishedAt: new Date().toISOString(),
      votes: { knew: 12, didntKnow: 9, grow: 5, end: 16 },
    },
  ],
  articleSources: [
    {
      id: 1,
      articleId: 1,
      sourceType: "official",
      title: "千鳥・大悟 新番組制作決定 独占配信プレスリリース",
      url: "https://press.example.com/chidori-show",
      publisher: "配信プラットフォーム公式",
      reliabilityScore: 100,
    },
    {
      id: 2,
      articleId: 1,
      sourceType: "news",
      title: "大悟 単独MCで新たな挑戦、公式ティザーが話題沸騰",
      url: "https://news.example.com/entame/123",
      publisher: "オリコンニュース",
      reliabilityScore: 90,
    },
    {
      id: 3,
      articleId: 2,
      sourceType: "official",
      title: "Monster Hunter Wilds タイトルアップデート第1弾ロードマップ",
      url: "https://capcom.example.com/mhw-update",
      publisher: "カプコン公式",
      reliabilityScore: 100,
    },
    {
      id: 4,
      articleId: 2,
      sourceType: "youtube",
      title: "【公式】Monster Hunter Wilds 第1弾大型アプデ紹介映像",
      url: "https://youtube.com/watch?v=M7lc1UVf-VE",
      publisher: "CAPCOM CHANNEL公式",
      reliabilityScore: 95,
    },
  ],
  imageGroups: [
    { id: 1, siteId: 1, name: "千鳥", genre: "entertainment", keywords: ["千鳥", "大悟", "ノブ", "お笑い", "芸人"] },
    { id: 2, siteId: 1, name: "ダウンタウン", genre: "entertainment", keywords: ["ダウンタウン", "浜田雅功", "松本人志", "バラエティ"] },
    { id: 3, siteId: 1, name: "モンスターハンター", genre: "game", keywords: ["モンスターハンター", "モンハン", "Wilds", "ハンター"] },
    { id: 4, siteId: 1, name: "スタジオジブリ", genre: "entertainment", keywords: ["ジブリ", "宮崎駿", "アニメ", "企画展"] },
  ],
  images: [
    {
      id: 1,
      siteId: 1,
      groupId: 1,
      groupName: "千鳥",
      filename: "chidori_001.webp",
      url: "https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=1000&q=80",
      altText: "お笑いステージマイクイメージ",
      isActive: true,
      useCount: 3,
      lastUsedAt: new Date(Date.now() - 3600 * 1000).toISOString(),
      keywords: ["千鳥", "大悟", "ノブ", "お笑い", "テレビ"],
    },
    {
      id: 2,
      siteId: 1,
      groupId: 2,
      groupName: "ダウンタウン",
      filename: "downtown_001.webp",
      url: "https://images.unsplash.com/photo-1475721027785-f74eccf877e2?auto=format&fit=crop&w=1000&q=80",
      altText: "スタジオ収録イメージ",
      isActive: true,
      useCount: 1,
      lastUsedAt: new Date(Date.now() - 86400 * 1000).toISOString(),
      keywords: ["ダウンタウン", "浜ちゃん", "松っちゃん", "バラエティ"],
    },
    {
      id: 3,
      siteId: 1,
      groupId: 3,
      groupName: "モンスターハンター",
      filename: "mhw_wilds_001.webp",
      url: "https://images.unsplash.com/photo-1538481199705-c710c4e965fc?auto=format&fit=crop&w=1000&q=80",
      altText: "大自然ハンティングイメージ",
      isActive: true,
      useCount: 5,
      lastUsedAt: new Date(Date.now() - 2 * 3600 * 1000).toISOString(),
      keywords: ["モンスターハンター", "ゲーム", "モンハン", "Wilds"],
    },
    {
      id: 4,
      siteId: 1,
      groupId: 4,
      groupName: "スタジオジブリ",
      filename: "ghibli_art_001.webp",
      url: "https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?auto=format&fit=crop&w=1000&q=80",
      altText: "アートミュージアム展示イメージ",
      isActive: true,
      useCount: 2,
      lastUsedAt: new Date(Date.now() - 70 * 3600 * 1000).toISOString(),
      keywords: ["ジブリ", "アニメ", "美術展", "チケット"],
    },
    {
      id: 5,
      siteId: 1,
      groupId: undefined,
      filename: "trend_general_001.webp",
      url: "https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=1000&q=80",
      altText: "総合ニュース速報イメージ",
      isActive: true,
      useCount: 12,
      lastUsedAt: new Date(Date.now() - 5000).toISOString(),
      keywords: ["トレンド", "ニュース", "話題"],
    },
  ],
  imageKeywords: [],
  votes: [],
  comments: [
    {
      id: 1,
      siteId: 1,
      articleId: 1,
      content: "大悟さんの単独冠番組めっちゃ楽しみです！ゲスト誰来るんだろう",
      status: "approved",
      createdAt: new Date(Date.now() - 30 * 60 * 1000).toISOString(),
    },
    {
      id: 2,
      siteId: 1,
      articleId: 1,
      content: "最後の「しらんけど。」でクスッときました笑",
      status: "approved",
      createdAt: new Date(Date.now() - 15 * 60 * 1000).toISOString(),
    },
    {
      id: 3,
      siteId: 1,
      articleId: 2,
      content: "アプデ待ってました！武器調整も入るのありがたい",
      status: "approved",
      createdAt: new Date(Date.now() - 45 * 60 * 1000).toISOString(),
    },
  ],
  bannedKeywords: [
    { id: 1, siteId: 1, keyword: "死ね", matchType: "partial", isActive: true, reason: "脅迫・中傷" },
    { id: 2, siteId: 1, keyword: "殺す", matchType: "partial", isActive: true, reason: "脅迫" },
    { id: 3, siteId: 1, keyword: "ガイジ", matchType: "partial", isActive: true, reason: "差別用語" },
    { id: 4, siteId: 1, keyword: "ゴミカス", matchType: "partial", isActive: true, reason: "侮辱" },
    { id: 5, siteId: 1, keyword: "電話番号", matchType: "partial", isActive: true, reason: "個人情報" },
    { id: 6, siteId: 1, keyword: "住所晒す", matchType: "partial", isActive: true, reason: "晒し" },
  ],
  snsQueue: [
    {
      id: 1,
      siteId: 1,
      articleId: 1,
      articleTitle: "千鳥・大悟の新バラエティが独占配信へ 公式発表にSNS歓喜",
      snsType: "x",
      postContent: "【話題度: 88/100】千鳥・大悟の新バラエティが独占配信へ 公式発表にSNS歓喜\n\nいま注目されているニュースをまとめました。しらんけど。\nhttps://example.com/article/trend-chidori-daigo-new-show\n#しらんけど #トレンド",
      scheduledAt: new Date(Date.now() - 10 * 60 * 1000).toISOString(),
      postedAt: new Date(Date.now() - 8 * 60 * 1000).toISOString(),
      status: "success",
      externalPostId: "x_post_1899120401",
    },
    {
      id: 2,
      siteId: 1,
      articleId: 1,
      articleTitle: "千鳥・大悟の新バラエティが独占配信へ 公式発表にSNS歓喜",
      snsType: "pinterest",
      postContent: "【千鳥・大悟の新バラエティが独占配信へ】\nしらんけど指数 88/100。\n大手配信プラットフォームが千鳥・大悟の単独MCによるオリジナル新番組を発表。",
      scheduledAt: new Date(Date.now() + 5 * 60 * 1000).toISOString(),
      status: "queued",
    },
    {
      id: 3,
      siteId: 1,
      articleId: 2,
      articleTitle: "Monster Hunter Wilds 無料大型アプデ第1弾の詳細公開",
      snsType: "x",
      postContent: "【話題度: 94/100】Monster Hunter Wilds 無料大型アプデ第1弾の詳細公開\nhttps://example.com/article/trend-mhw-wilds-update-1\n#しらんけど #モンハン",
      scheduledAt: new Date(Date.now() - 20 * 60 * 1000).toISOString(),
      postedAt: new Date(Date.now() - 18 * 60 * 1000).toISOString(),
      status: "success",
      externalPostId: "x_post_1899121980",
    },
  ],
  siteSettings: {
    1: {
      siteId: 1,
      weights: { google: 30, yahoo: 25, news: 20, youtube: 15, game: 10 },
      indexLabels: { min0: "ちょい話題", min30: "話題", min60: "かなり話題", min80: "めっちゃ話題" },
      rapidRiseThreshold: 100,
      aiProvider: "gemini",
      aiModel: "gemini-3.8-flash",
      aiMaxChars: 600,
      aiTemperature: 0.4,
      aiSystemPrompt: "客観的な事実のみをまとめ、末尾は必ず「〜しらんけど。」で締める。",
      sns: { xEnabled: true, pinterestEnabled: true, instagramEnabled: true, dailyLimit: 20 },
    },
  },
  logs: [
    {
      id: 1,
      siteId: 1,
      category: "trend_fetch",
      message: "Googleトレンド・Yahoo・YouTube・ゲームランキングの自動収集完了 (新規2件/更新3件)",
      createdAt: new Date(Date.now() - 2 * 3600 * 1000).toISOString(),
    },
    {
      id: 2,
      siteId: 1,
      category: "safety_brake",
      message: "安全ブレーキ作動: 某容疑者SNS特定デマ騒動 を自動保留に設定 (理由: 危険キーワード検知)",
      createdAt: new Date(Date.now() - 1 * 3600 * 1000).toISOString(),
    },
    {
      id: 3,
      siteId: 1,
      category: "ai_gen",
      message: "AI記事生成完了: 千鳥・大悟の新バラエティが独占配信へ (Gemini 3.8 Flash)",
      createdAt: new Date(Date.now() - 1.5 * 3600 * 1000).toISOString(),
    },
    {
      id: 4,
      siteId: 1,
      category: "sns_post",
      message: "X(Twitter)への自動投稿キュー配信成功 (Post ID: x_post_1899120401)",
      createdAt: new Date(Date.now() - 8 * 60 * 1000).toISOString(),
    },
  ],
};

// Helper: resolve current site from subdomain/header/query
function resolveSite(req: Request) {
  const querySiteId = req.query.site_id ? parseInt(req.query.site_id as string, 10) : null;
  if (querySiteId) {
    const s = store.sites.find((item) => item.id === querySiteId);
    if (s) return s;
  }

  const host = req.headers["x-site-subdomain"] || req.headers.host || "";
  const hostStr = Array.isArray(host) ? host[0] : host;
  const parts = hostStr.split(".")[0];
  const matched = store.sites.find((s) => s.subdomain === parts);
  return matched || store.sites[0];
}

// ----------------------------------------------------
// API ROUTES
// ----------------------------------------------------

// 1. Current Site Info & All Sites (Multi-site)
app.get("/api/sites", (req: Request, res: Response) => {
  res.json({ sites: store.sites, current: resolveSite(req) });
});

app.post("/api/sites", (req: Request, res: Response) => {
  const { subdomain, name, description, genre, allowAutoPublish } = req.body;
  const newSite = {
    id: store.sites.length + 1,
    subdomain: (subdomain || "").toLowerCase().trim(),
    name: name || "新しいトレンドサイト",
    description: description || "",
    genre: genre || "general",
    logoUrl: "",
    isPublic: true,
    allowAutoPublish: allowAutoPublish !== false,
    youtubeThumbnailEnabled: true,
  };
  store.sites.push(newSite);
  res.json({ success: true, site: newSite });
});

// 2. Categories
app.get("/api/categories", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const cats = store.categories.filter((c) => c.siteId === site.id || c.siteId === 1);
  res.json({ categories: cats });
});

// 3. Articles (Front & Admin)
app.get("/api/articles", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const { status, category, limit, sort } = req.query;

  let list = store.articles.filter((a) => a.siteId === site.id || site.id === 1);

  if (status) {
    list = list.filter((a) => a.status === status);
  } else if (!req.query.all_status) {
    // Default front view: only published
    list = list.filter((a) => a.status === "published");
  }

  if (category && category !== "all") {
    const cat = store.categories.find((c) => c.slug === category);
    if (cat) {
      list = list.filter((a) => a.categoryId === cat.id);
    }
  }

  if (sort === "rapid") {
    list.sort((a, b) => (b.isRapidRise ? 1 : 0) - (a.isRapidRise ? 1 : 0) || b.growthRate - a.growthRate);
  } else if (sort === "index") {
    list.sort((a, b) => b.shirankedoIndex - a.shirankedoIndex);
  } else {
    list.sort((a, b) => new Date(b.publishedAt).getTime() - new Date(a.publishedAt).getTime());
  }

  if (limit) {
    list = list.slice(0, parseInt(limit as string, 10));
  }

  res.json({ articles: list });
});

// 4. Article Detail
app.get("/api/articles/:slugOrId", (req: Request, res: Response) => {
  const { slugOrId } = req.params;
  const isId = /^\d+$/.test(slugOrId);
  const article = store.articles.find((a) => (isId ? a.id === parseInt(slugOrId, 10) : a.slug === slugOrId));

  if (!article) {
    res.status(404).json({ error: "記事が見つかりません" });
    return;
  }

  const sources = store.articleSources.filter((s) => s.articleId === article.id);
  const comments = store.comments.filter((c) => c.articleId === article.id && c.status === "approved");

  res.json({
    article: {
      ...article,
      sources,
      commentsCount: comments.length,
    },
  });
});

// 5. User Voting API (知ってた/知らんかった, もっと伸びる/もう終わる)
app.post("/api/articles/:id/vote", (req: Request, res: Response) => {
  const articleId = parseInt(req.params.id, 10);
  const { pollType, voteValue } = req.body;
  const article = store.articles.find((a) => a.id === articleId);

  if (!article) {
    res.status(404).json({ error: "記事が存在しません" });
    return;
  }

  if (!article.votes) {
    article.votes = { knew: 0, didntKnow: 0, grow: 0, end: 0 };
  }

  if (pollType === "knew_ratio") {
    if (voteValue === "knew") article.votes.knew++;
    else article.votes.didntKnow++;
  } else if (pollType === "future_growth") {
    if (voteValue === "grow") article.votes.grow++;
    else article.votes.end++;
  }

  res.json({ success: true, votes: article.votes });
});

// 6. User Comments API (文字のみ・URL禁止・拒否キーワード自動遮断)
app.get("/api/articles/:id/comments", (req: Request, res: Response) => {
  const articleId = parseInt(req.params.id, 10);
  const comments = store.comments.filter((c) => c.articleId === articleId && c.status === "approved");
  res.json({ comments });
});

app.post("/api/articles/:id/comments", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const articleId = parseInt(req.params.id, 10);
  const { content } = req.body;

  if (!content || typeof content !== "string" || !content.trim()) {
    res.status(400).json({ error: "コメント内容を入力してください。" });
    return;
  }

  // 1. URL検知 (URL投稿禁止)
  const urlPattern = /https?:\/\/|ftp:\/\/|www\.[a-z0-9\-]+\.[a-z]{2,}|[a-z0-9\-]+\.(com|net|org|jp|io|me|app)/i;
  if (urlPattern.test(content)) {
    res.status(400).json({
      error: "当サイトではURLの投稿は禁止されています。文字のみで投稿してください。",
    });
    return;
  }

  // 2. 拒否キーワード照合
  const banned = store.bannedKeywords.filter((k) => (k.siteId === site.id || k.siteId === 1) && k.isActive);
  for (const b of banned) {
    if (b.matchType === "exact" && content.trim() === b.keyword) {
      res.status(400).json({ error: "禁止キーワードが含まれているため投稿できません。" });
      return;
    } else if (b.matchType === "partial" && content.includes(b.keyword)) {
      res.status(400).json({ error: "誹謗中傷・不適切な言葉が含まれているため投稿できません。" });
      return;
    }
  }

  // 3. HTML除去 & プレーンテキスト化
  const cleaned = content.replace(/<[^>]*>/g, "").trim().slice(0, 400);

  const newComment = {
    id: store.comments.length + 1,
    siteId: site.id,
    articleId,
    content: cleaned,
    status: "approved",
    createdAt: new Date().toISOString(),
  };
  store.comments.unshift(newComment);

  res.json({ success: true, comment: newComment });
});

// 7. Trend Candidates & Auto-Fetch Trigger
app.get("/api/trends", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const trends = store.trendCandidates.filter((t) => t.siteId === site.id || site.id === 1);
  res.json({ trends });
});

app.post("/api/trends/collect", (req: Request, res: Response) => {
  const site = resolveSite(req);
  // Simulate gathering new real-world trend
  const sampleTopics = [
    {
      keyword: "新オープンプラットフォーム発表",
      sources: ["google", "news"],
      growth: 125,
      google: 90,
      yahoo: 70,
      news: 85,
      youtube: 40,
      game: 0,
    },
    {
      keyword: "国民的アニメ映画 最新予告PV解禁",
      sources: ["youtube", "yahoo", "google"],
      growth: 180,
      google: 88,
      yahoo: 95,
      news: 80,
      youtube: 100,
      game: 20,
    },
  ];

  let added = 0;
  sampleTopics.forEach((t) => {
    const exists = store.trendCandidates.some((tc) => tc.displayKeyword.includes(t.keyword));
    if (!exists) {
      const idx = Math.round((t.google * 0.3 + t.yahoo * 0.25 + t.news * 0.2 + t.youtube * 0.15 + t.game * 0.1));
      store.trendCandidates.unshift({
        id: store.trendCandidates.length + 1,
        siteId: site.id,
        normalizedKeyword: t.keyword.replace(/\s+/g, "").toLowerCase(),
        displayKeyword: t.keyword,
        sources: t.sources,
        googleScore: t.google,
        yahooScore: t.yahoo,
        youtubeScore: t.youtube,
        newsScore: t.news,
        gameScore: t.game,
        shirankedoIndex: idx,
        isRapidRise: t.growth >= 100,
        growthRate: t.growth,
        firstDetectedAt: new Date().toISOString(),
        lastUpdatedAt: new Date().toISOString(),
        status: "candidate",
      });
      added++;
    }
  });

  store.logs.unshift({
    id: store.logs.length + 1,
    siteId: site.id,
    category: "trend_fetch",
    message: `トレンド手動収集を実行: 新規${added}件の候補を取得`,
    createdAt: new Date().toISOString(),
  });

  res.json({ success: true, addedCount: added });
});

// 8. AI Article Generation Trigger (Uses Gemini 3.8 Flash server-side)
app.post("/api/articles/generate", async (req: Request, res: Response) => {
  const site = resolveSite(req);
  const { trendCandidateId, customKeyword } = req.body;

  let keyword = customKeyword;
  let candidate = null;
  if (trendCandidateId) {
    candidate = store.trendCandidates.find((t) => t.id === trendCandidateId);
    if (candidate) keyword = candidate.displayKeyword;
  }

  if (!keyword) {
    res.status(400).json({ error: "対象キーワードを指定してください" });
    return;
  }

  // 1. Safety Brake Check (危険ジャンル検知)
  const dangerWords = [
    "犯罪", "逮捕", "容疑", "不倫", "離婚", "薬物", "病気", "訃報", "死亡", "自殺",
    "事故", "事故責任", "金銭トラブル", "未成年", "一般人", "個人情報", "流出"
  ];
  const matchedDanger = dangerWords.filter((w) => keyword.includes(w));
  const isDangerous = matchedDanger.length > 0;

  // 2. Prepare Primary Sources
  const verifiedSources = [
    {
      id: Date.now(),
      articleId: 0,
      sourceType: "news" as const,
      title: `「${keyword}」に関する公式プレスリリース及び報道速報`,
      publisher: "主要報道機関・公式発表",
      url: `https://news.example.com/topic/${encodeURIComponent(keyword)}`,
      reliabilityScore: 95,
    },
  ];

  // 3. AI Generation via Gemini or fallback
  let generatedTitle = `「${keyword}」がネットで急上昇、注目が集まる`;
  let whyTrending = `複数のトレンド指標および検索ボリュームで「${keyword}」が急伸。公式発表を受けて話題となっています。`;
  let bodyText = `本日、「${keyword}」に関する最新情報が発表され、大きな反響を呼んでいます。\n\n確認された公式発表および報道資料によると、今回の発表は以前より注目されていた内容の具体化であり、多くのファンや関係者の間で話題が急拡大しています。\n\n詳細な仕様や今後のスケジュールについては、順次公式アナウンスが行われる見通しです。`;
  let conclusionSentence = "今後の追加発表次第では、さらに盛り上がりを見せる展開になるかもしれません。しらんけど。";

  try {
    if (process.env.GEMINI_API_KEY) {
      const ai = getAi();
      const prompt = `あなたはトレンドニュースサイト「しらんけど」のAIエディターです。
以下のキーワードに関する客観的な事実要約記事を作成してください。
【キーワード】: ${keyword}

【厳格ルール】
1. 噂や憶測を事実として書かないこと。
2. 容疑段階の人物を犯人扱いせず、一般人の個人情報は書かないこと。
3. 記事本文の末尾は、文脈に沿った自然な日本語の余韻として、必ず「〜しらんけど。」で締めてください。
4. JSON形式のみで出力してください:
{
  "title": "35文字以内のタイトル",
  "whyTrending": "なぜ話題かの100文字要約",
  "body": "段落分けされた客観的本文（300〜500文字）",
  "conclusion": "末尾の一文（最後は必ず「〜しらんけど。」）"
}`;

      const response = await ai.models.generateContent({
        model: "gemini-3.8-flash",
        contents: prompt,
        config: {
          responseMimeType: "application/json",
          temperature: 0.4,
        },
      });

      if (response.text) {
        const parsed = JSON.parse(response.text);
        if (parsed.title) generatedTitle = parsed.title;
        if (parsed.whyTrending) whyTrending = parsed.whyTrending;
        if (parsed.body) bodyText = parsed.body;
        if (parsed.conclusion) conclusionSentence = parsed.conclusion;
      }
    }
  } catch (err: any) {
    console.error("Gemini Generation fallback:", err.message);
  }

  // Ensure conclusion ends with 「しらんけど。」
  if (!conclusionSentence.endsWith("しらんけど。")) {
    conclusionSentence = conclusionSentence.replace(/。?$/, "") + "。しらんけど。";
  }

  // 4. Image matching logic (dedicated -> group -> generic)
  const matchedGroup = store.imageGroups.find((g) => g.keywords.some((k: string) => keyword.includes(k)));
  let selectedImage = store.images.find((img) => matchedGroup && img.groupId === matchedGroup.id);
  if (!selectedImage) {
    selectedImage = store.images[store.images.length - 1];
  }
  selectedImage.useCount++;
  selectedImage.lastUsedAt = new Date().toISOString();

  // 5. Status: If dangerous -> 'on_hold' (Safety Brake), else 'published'
  const finalStatus = isDangerous || !site.allowAutoPublish ? "on_hold" : "published";

  const newArticle = {
    id: store.articles.length + 1,
    siteId: site.id,
    categoryId: 1,
    categoryName: "総合",
    title: generatedTitle,
    slug: `trend-${Date.now()}-${Math.floor(Math.random() * 900 + 100)}`,
    whyTrending,
    body: bodyText,
    conclusionSentence,
    shirankedoIndex: candidate ? candidate.shirankedoIndex : 75,
    indexLabel: candidate && candidate.shirankedoIndex >= 80 ? "めっちゃ話題" : "かなり話題",
    isRapidRise: candidate ? candidate.isRapidRise : true,
    growthRate: candidate ? candidate.growthRate : 120.0,
    firstDetectedAt: candidate ? candidate.firstDetectedAt : new Date().toISOString(),
    imageUrl: selectedImage.url,
    status: finalStatus,
    isDangerous,
    dangerReason: isDangerous ? `危険ジャンル検知: ${matchedDanger.join(", ")}` : undefined,
    publishedAt: new Date().toISOString(),
    votes: { knew: 0, didntKnow: 0, grow: 0, end: 0 },
  };

  store.articles.unshift(newArticle);

  if (candidate) {
    candidate.status = "completed";
  }

  // Enqueue SNS if published
  if (finalStatus === "published") {
    store.snsQueue.unshift({
      id: store.snsQueue.length + 1,
      siteId: site.id,
      articleId: newArticle.id,
      articleTitle: newArticle.title,
      snsType: "x",
      postContent: `【話題度: ${newArticle.shirankedoIndex}/100】${newArticle.title}\n\nいま注目されている話題をチェック。しらんけど。\nhttps://example.com/article/${newArticle.slug}\n#しらんけど #トレンド`,
      scheduledAt: new Date(Date.now() + 5 * 60 * 1000).toISOString(),
      status: "queued",
    });
  }

  store.logs.unshift({
    id: store.logs.length + 1,
    siteId: site.id,
    category: isDangerous ? "safety_brake" : "ai_gen",
    message: isDangerous
      ? `安全ブレーキ作動: 「${newArticle.title}」を自動保留に設定`
      : `AI記事生成完了: 「${newArticle.title}」`,
    createdAt: new Date().toISOString(),
  });

  res.json({ success: true, article: newArticle });
});

// 9. Article Moderation (Approve held, Change status)
app.patch("/api/articles/:id/status", (req: Request, res: Response) => {
  const articleId = parseInt(req.params.id, 10);
  const { status } = req.body;
  const article = store.articles.find((a) => a.id === articleId);

  if (!article) {
    res.status(404).json({ error: "記事が見つかりません" });
    return;
  }

  article.status = status;
  if (status === "published" && !article.publishedAt) {
    article.publishedAt = new Date().toISOString();
  }

  res.json({ success: true, article });
});

// 10. Image Management & Groups
app.get("/api/images", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const images = store.images.filter((img) => img.siteId === site.id || site.id === 1);
  const groups = store.imageGroups.filter((g) => g.siteId === site.id || site.id === 1);
  res.json({ images, groups });
});

app.post("/api/images", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const { filename, url, altText, groupId, keywords } = req.body;
  const newImg = {
    id: store.images.length + 1,
    siteId: site.id,
    groupId: groupId ? parseInt(groupId, 10) : undefined,
    filename: filename || `img_${Date.now()}.webp`,
    url: url || "https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=1000&q=80",
    altText: altText || "イラスト・イメージ",
    isActive: true,
    useCount: 0,
    keywords: Array.isArray(keywords) ? keywords : (keywords || "").split(",").map((k: string) => k.trim()),
  };
  store.images.unshift(newImg);
  res.json({ success: true, image: newImg });
});

app.post("/api/image-groups", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const { name, genre, keywords } = req.body;
  const newGroup = {
    id: store.imageGroups.length + 1,
    siteId: site.id,
    name: name || "新規グループ",
    genre: genre || "general",
    keywords: Array.isArray(keywords) ? keywords : (keywords || "").split(",").map((k: string) => k.trim()),
  };
  store.imageGroups.push(newGroup);
  res.json({ success: true, group: newGroup });
});

// 11. Banned Keywords
app.get("/api/banned-keywords", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const keywords = store.bannedKeywords.filter((k) => k.siteId === site.id || site.id === 1);
  res.json({ keywords });
});

app.post("/api/banned-keywords", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const { keyword, matchType, reason } = req.body;
  const newKw = {
    id: store.bannedKeywords.length + 1,
    siteId: site.id,
    keyword: keyword.trim(),
    matchType: matchType || "partial",
    isActive: true,
    reason: reason || "管理規定による遮断",
  };
  store.bannedKeywords.push(newKw);
  res.json({ success: true, keyword: newKw });
});

app.delete("/api/banned-keywords/:id", (req: Request, res: Response) => {
  const id = parseInt(req.params.id, 10);
  store.bannedKeywords = store.bannedKeywords.filter((k) => k.id !== id);
  res.json({ success: true });
});

// 12. SNS Queue & Dispatch
app.get("/api/sns-queue", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const queue = store.snsQueue.filter((q) => q.siteId === site.id || site.id === 1);
  res.json({ queue });
});

app.post("/api/sns-queue/process", (req: Request, res: Response) => {
  let processed = 0;
  store.snsQueue.forEach((item) => {
    if (item.status === "queued") {
      item.status = "success";
      item.postedAt = new Date().toISOString();
      item.externalPostId = `${item.snsType}_post_${Date.now()}`;
      processed++;
    }
  });
  res.json({ success: true, processedCount: processed });
});

// 13. Site Settings (Weights, AI, SNS, etc.)
app.get("/api/settings", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const settings = store.siteSettings[site.id] || store.siteSettings[1];
  res.json({ settings });
});

app.post("/api/settings", (req: Request, res: Response) => {
  const site = resolveSite(req);
  store.siteSettings[site.id] = {
    ...store.siteSettings[site.id],
    ...req.body,
    siteId: site.id,
  };
  res.json({ success: true, settings: store.siteSettings[site.id] });
});

// 14. Logs
app.get("/api/logs", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const logs = store.logs.filter((l) => !l.siteId || l.siteId === site.id || site.id === 1);
  res.json({ logs });
});

// 15. Dashboard Stats
app.get("/api/dashboard-stats", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const siteArticles = store.articles.filter((a) => a.siteId === site.id || site.id === 1);
  const siteTrends = store.trendCandidates.filter((t) => t.siteId === site.id || site.id === 1);
  const siteSns = store.snsQueue.filter((s) => s.siteId === site.id || site.id === 1);

  const stats = {
    todayTrendsCount: siteTrends.length,
    generatedArticlesCount: siteArticles.length,
    autoPublishedCount: siteArticles.filter((a) => a.status === "published").length,
    heldCount: siteArticles.filter((a) => a.status === "on_hold").length,
    snsSuccessCount: siteSns.filter((s) => s.status === "success").length,
    snsErrorCount: siteSns.filter((s) => s.status === "failed").length,
    commentsCount: store.comments.filter((c) => c.siteId === site.id || site.id === 1).length,
  };

  res.json({ stats, currentSite: site });
});

// 16. SEO: sitemap.xml & robots.txt
app.get("/sitemap.xml", (req: Request, res: Response) => {
  res.header("Content-Type", "application/xml");
  let xml = `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n`;
  xml += `  <url><loc>https://example.com/</loc><priority>1.0</priority></url>\n`;
  xml += `  <url><loc>https://example.com/shirankedo-about</loc><priority>0.8</priority></url>\n`;
  xml += `  <url><loc>https://example.com/comment-rules</loc><priority>0.8</priority></url>\n`;
  store.articles.filter((a) => a.status === "published").forEach((a) => {
    xml += `  <url><loc>https://example.com/article/${a.slug}</loc><lastmod>${new Date(a.publishedAt).toISOString().split("T")[0]}</lastmod></url>\n`;
  });
  xml += `</urlset>`;
  res.send(xml);
});

app.get("/robots.txt", (req: Request, res: Response) => {
  res.type("text/plain");
  res.send(`User-agent: *\nAllow: /\nDisallow: /admin\nSitemap: https://example.com/sitemap.xml\n`);
});

// ----------------------------------------------------
// Start Server with Vite Middleware
// ----------------------------------------------------
async function startServer() {
  if (process.env.NODE_ENV !== "production") {
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: "spa",
    });
    app.use(vite.middlewares);
  } else {
    const distPath = path.join(process.cwd(), "dist");
    app.use(express.static(distPath));
    app.get("*", (req, res) => {
      res.sendFile(path.join(distPath, "index.html"));
    });
  }

  app.listen(PORT, "0.0.0.0", () => {
    console.log(`「しらんけど」サーバー起動完了: http://localhost:${PORT}`);
  });
}

startServer();
