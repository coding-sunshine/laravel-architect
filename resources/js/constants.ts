export const DRAFT_HISTORY_KEY = 'architect-draft-history';
export const LARAVEL_RELATIONSHIPS_URL = 'https://laravel.com/docs/eloquent-relationships';
export const DRAFT_HISTORY_MAX = 5;

export const STARTER_SUMMARIES: Record<string, string> = {
    blog: 'User, Post, Comment',
    api: 'ApiKey',
    saas: 'Team, Project',
};

export const RELATION_LABELS: Record<string, string> = {
    belongsTo: 'belongs to',
    hasMany: 'has many',
    hasOne: 'has one',
    belongsToMany: 'belongs to many',
    morphTo: 'morph to',
    morphMany: 'morph many',
};
