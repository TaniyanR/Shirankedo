export type PollType = 'knew_ratio' | 'future_growth';

export type ArticleStatus = 
  | 'candidate' 
  | 'collecting' 
  | 'ai_pending' 
  | 'generated' 
  | 'review_pending' 
  | 'on_hold' 
  | 'scheduled' 
  | 'published' 
  | 'private' 
  | 'error';

export interface Site {
  id: number;
  subdomain: string;
  name: string;
  description: string;
  genre: string;
  logoUrl?: string;
  isPublic: boolean;
  allowAutoPublish: boolean;
  youtubeThumbnailEnabled: boolean;
}

export interface Category {
  id: number;
  siteId: number;
  slug: string;
  name: string;
  sortOrder: number;
}

export interface TrendCandidate {
  id: number;
  siteId: number;
  normalizedKeyword: string;
  displayKeyword: string;
  sources: ('google' | 'yahoo' | 'youtube' | 'news' | 'game')[];
  googleScore: number;
  yahooScore: number;
  youtubeScore: number;
  newsScore: number;
  gameScore: number;
  shirankedoIndex: number;
  isRapidRise: boolean;
  growthRate: number;
  firstDetectedAt: string;
  lastUpdatedAt: string;
  status: 'candidate' | 'verified' | 'processing' | 'completed' | 'ignored';
}

export interface ArticleSource {
  id: number;
  articleId: number;
  sourceType: 'official' | 'news' | 'youtube' | 'other';
  title: string;
  url: string;
  publisher: string;
  reliabilityScore: number;
}

export interface Article {
  id: number;
  siteId: number;
  categoryId?: number;
  categoryName?: string;
  title: string;
  slug: string;
  whyTrending: string;
  body: string;
  conclusionSentence: string;
  shirankedoIndex: number;
  indexLabel: string;
  isRapidRise: boolean;
  growthRate: number;
  firstDetectedAt: string;
  imageUrl?: string;
  youtubeVideoId?: string;
  status: ArticleStatus;
  isDangerous: boolean;
  dangerReason?: string;
  publishedAt: string;
  sources?: ArticleSource[];
  votes?: {
    knew: number;
    didntKnow: number;
    grow: number;
    end: number;
  };
  commentsCount?: number;
}

export interface Comment {
  id: number;
  siteId: number;
  articleId: number;
  content: string;
  status: 'approved' | 'pending' | 'hidden' | 'deleted';
  createdAt: string;
}

export interface ImageGroup {
  id: number;
  siteId: number;
  name: string;
  genre: string;
  keywords: string[];
}

export interface ImageItem {
  id: number;
  siteId: number;
  groupId?: number;
  groupName?: string;
  categoryId?: number;
  filename: string;
  url: string;
  altText: string;
  isActive: boolean;
  useCount: number;
  lastUsedAt?: string;
  keywords: string[];
}

export interface BannedKeyword {
  id: number;
  siteId: number;
  keyword: string;
  matchType: 'exact' | 'partial' | 'regex';
  isActive: boolean;
  reason: string;
}

export interface SnsQueueItem {
  id: number;
  siteId: number;
  articleId: number;
  articleTitle?: string;
  snsType: 'x' | 'pinterest' | 'instagram';
  postContent: string;
  imageUrl?: string;
  scheduledAt: string;
  postedAt?: string;
  status: 'queued' | 'processing' | 'success' | 'failed';
  externalPostId?: string;
  errorMessage?: string;
}

export interface SystemLog {
  id: number;
  siteId?: number;
  category: 'trend_fetch' | 'ai_gen' | 'article_publish' | 'safety_brake' | 'sns_post' | 'image_select' | 'api_error' | 'admin_action';
  message: string;
  details?: Record<string, unknown>;
  createdAt: string;
}

export interface DashboardStats {
  todayTrendsCount: number;
  generatedArticlesCount: number;
  autoPublishedCount: number;
  heldCount: number;
  snsSuccessCount: number;
  snsErrorCount: number;
  commentsCount: number;
}

export interface SiteSettings {
  siteId: number;
  weights: {
    google: number;
    yahoo: number;
    news: number;
    youtube: number;
    game: number;
  };
  indexLabels: {
    min0: string;
    min30: string;
    min60: string;
    min80: string;
  };
  rapidRiseThreshold: number;
  aiProvider: 'gemini' | 'openai';
  aiModel: string;
  aiMaxChars: number;
  aiTemperature: number;
  aiSystemPrompt: string;
  sns: {
    xEnabled: boolean;
    pinterestEnabled: boolean;
    instagramEnabled: boolean;
    dailyLimit: number;
  };
}
