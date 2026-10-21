export interface FrontendSlider {
    id: number;
    title: string;
    subtitle: string | null;
    badge: string | null;
    primary_button_text: string | null;
    primary_button_url: string | null;
    secondary_button_text: string | null;
    secondary_button_url: string | null;
    image_path: string | null;
    image_url: string | null;
    sort_order: number;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface FrontendAnnouncement {
    id: number;
    title: string;
    slug: string;
    category: string;
    summary: string | null;
    content: string;
    cover_image_path: string | null;
    cover_image_url: string | null;
    attachment_path: string | null;
    attachment_name: string | null;
    attachment_url: string | null;
    is_pinned: boolean;
    is_published: boolean;
    published_at: string | null;
    author_id: number | null;
    author?: {
        id: number;
        name: string;
        username: string;
    } | null;
    created_at?: string;
    updated_at?: string;
}

export interface FrontendGallery {
    id: number;
    title: string;
    category: string;
    image_path: string;
    image_url: string | null;
    caption: string | null;
    sort_order: number;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface FrontendFaq {
    id: number;
    category: string;
    question: string;
    answer: string;
    sort_order: number;
    is_published: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface FrontendContactMessage {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    subject: string;
    message: string;
    status: 'unread' | 'read' | 'replied' | 'archived';
    admin_notes: string | null;
    replied_at: string | null;
    replied_by: number | null;
    created_at: string;
    updated_at: string;
}
