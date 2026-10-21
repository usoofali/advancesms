<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Award,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    Download,
    Eye,
    FileText,
    GraduationCap,
    HelpCircle,
    Image as ImageIcon,
    Layers,
    Mail,
    MapPin,
    Megaphone,
    MessageSquare,
    Pencil,
    Phone,
    Plus,
    Sliders,
    Sparkles,
    Trash2,
    UploadCloud,
    User,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/school-settings';
import {
    destroy as destroyGradeRoute,
    store as storeGradeRoute,
    update as updateGradeRoute,
} from '@/routes/school-settings/grades';
import type { Grade } from '@/types/academic';
import type {
    FrontendAnnouncement,
    FrontendContactMessage,
    FrontendFaq,
    FrontendGallery,
    FrontendSlider,
} from '@/types/frontend';
import type { SchoolSetting } from '@/types/school';

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface Props {
    settings: SchoolSetting;
    grades?: Grade[];
    sliders?: FrontendSlider[];
    announcements?: PaginatedData<FrontendAnnouncement>;
    galleries?: FrontendGallery[];
    faqs?: FrontendFaq[];
    inquiries?: PaginatedData<FrontendContactMessage>;
}

const props = withDefaults(defineProps<Props>(), {
    grades: () => [],
    sliders: () => [],
    galleries: () => [],
    faqs: () => [],
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'School Profile & CMS',
                href: edit().url,
            },
        ],
    },
});

const page = usePage();

const canUpdate = computed(() => {
    const permissions = page.props.auth?.permissions as string[] | undefined;
    const roles = page.props.auth?.roles as string[] | undefined;
    return (
        permissions?.includes('*') ||
        roles?.includes('superadmin') ||
        permissions?.includes('school_settings.update') ||
        permissions?.includes('frontend_cms.update')
    );
});

// Active Tab
type TabType = 'profile' | 'grading' | 'cms_story' | 'sliders' | 'announcements' | 'gallery' | 'faqs' | 'inquiries';
const activeTab = ref<TabType>('profile');

// -----------------------------------------------------------------------------
// School Settings Form (Profile & CMS Story)
// -----------------------------------------------------------------------------
const coreValuesInput = ref((props.settings.core_values || []).join(', '));
const socialFb = ref(props.settings.social_links?.facebook || '');
const socialTw = ref(props.settings.social_links?.x_twitter || '');
const socialInsta = ref(props.settings.social_links?.instagram || '');

const form = useForm({
    school_name: props.settings.school_name || '',
    school_code: props.settings.school_code || '',
    motto: props.settings.motto || '',
    address: props.settings.address || '',
    phone: props.settings.phone || '',
    email: props.settings.email || '',
    currency_symbol: props.settings.currency_symbol || '₦',
    currency_code: props.settings.currency_code || 'NGN',
    principal_name: props.settings.principal_name || '',
    ca_count: props.settings.ca_count ?? 3,
    default_max_ca1: props.settings.default_max_ca1 ?? 10.0,
    default_max_ca2: props.settings.default_max_ca2 ?? 10.0,
    default_max_ca3: props.settings.default_max_ca3 ?? 10.0,
    default_max_exam: props.settings.default_max_exam ?? 70.0,
    ca1_label: props.settings.ca1_label ?? '1st CA',
    ca2_label: props.settings.ca2_label ?? '2nd CA',
    ca3_label: props.settings.ca3_label ?? '3rd CA',
    about_text: props.settings.about_text || '',
    mission: props.settings.mission || '',
    vision: props.settings.vision || '',
    core_values: props.settings.core_values || [],
    principal_message: props.settings.principal_message || '',
    office_hours: props.settings.office_hours || 'Mon - Fri: 8:00 AM - 3:30 PM',
    map_embed_url: props.settings.map_embed_url || '',
    social_links: props.settings.social_links || {},
    logo: null as File | null,
    stamp: null as File | null,
    principal_photo: null as File | null,
    prospectus: null as File | null,
    letterhead_file: null as File | null,
    letterhead_base64: props.settings.letterhead_base64 || '',
    letterhead_top_margin: props.settings.letterhead_top_margin ?? 120,
    letterhead_bottom_margin: props.settings.letterhead_bottom_margin ?? 50,
    letterhead_left_margin: props.settings.letterhead_left_margin ?? 40,
    letterhead_right_margin: props.settings.letterhead_right_margin ?? 40,
});

const logoPreview = ref<string | null>(null);
const stampPreview = ref<string | null>(null);
const principalPhotoPreview = ref<string | null>(null);
const letterheadPreview = ref<string | null>(props.settings.letterhead_base64 || null);

const activeLogoUrl = computed(() => logoPreview.value || props.settings.logo_url);
const activeStampUrl = computed(() => stampPreview.value || props.settings.stamp_url);
const activePrincipalPhotoUrl = computed(() => principalPhotoPreview.value || props.settings.principal_photo_url);

const handleLetterheadChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        if (file.size > 5 * 1024 * 1024) {
            toast.error('Letterhead file size cannot exceed 5MB');
            return;
        }
        form.letterhead_file = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            const result = e.target?.result as string;
            letterheadPreview.value = result;
            form.letterhead_base64 = result;
        };
        reader.readAsDataURL(file);
    }
};

const removeLetterhead = () => {
    form.letterhead_file = null;
    form.letterhead_base64 = '';
    letterheadPreview.value = null;
};

const handleLogoChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        if (file.size > 2 * 1024 * 1024) {
            toast.error('Logo file size cannot exceed 2MB');
            return;
        }
        form.logo = file;
        logoPreview.value = URL.createObjectURL(file);
    }
};

const handleStampChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        if (file.size > 2 * 1024 * 1024) {
            toast.error('Stamp file size cannot exceed 2MB');
            return;
        }
        form.stamp = file;
        stampPreview.value = URL.createObjectURL(file);
    }
};

const handlePrincipalPhotoChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        if (file.size > 2 * 1024 * 1024) {
            toast.error('Photo size cannot exceed 2MB');
            return;
        }
        form.principal_photo = file;
        principalPhotoPreview.value = URL.createObjectURL(file);
    }
};

const handleProspectusChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        if (file.size > 10 * 1024 * 1024) {
            toast.error('Prospectus PDF cannot exceed 10MB');
            return;
        }
        form.prospectus = file;
        toast.info(`Selected ${file.name} for prospectus upload`);
    }
};

const submitSchoolSettings = () => {
    // Parse core values array
    form.core_values = coreValuesInput.value
        .split(',')
        .map((s) => s.trim())
        .filter((s) => s.length > 0);

    // Parse social links
    form.social_links = {
        facebook: socialFb.value.trim(),
        x_twitter: socialTw.value.trim(),
        instagram: socialInsta.value.trim(),
    };

    form.post('/settings/school', {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Settings and profile updated successfully');
        },
        onError: () => {
            toast.error('Please check form validation errors');
        },
    });
};

// -----------------------------------------------------------------------------
// Continuous Assessment (CA) Configuration Helpers
// -----------------------------------------------------------------------------
const totalAssessmentWeight = computed(() => {
    const ca1 = Number(form.default_max_ca1) || 0;
    const ca2 = Number(form.ca_count) >= 2 ? (Number(form.default_max_ca2) || 0) : 0;
    const ca3 = Number(form.ca_count) >= 3 ? (Number(form.default_max_ca3) || 0) : 0;
    const exam = Number(form.default_max_exam) || 0;
    return Number((ca1 + ca2 + ca3 + exam).toFixed(2));
});

const onCaCountSelect = (count: number) => {
    form.ca_count = count;
    if (count === 1) {
        if (!form.ca1_label || form.ca1_label === '1st CA') form.ca1_label = 'Continuous Assessment';
        if (Number(form.default_max_ca1) === 10) form.default_max_ca1 = 30;
        if (Number(form.default_max_exam) === 70) form.default_max_exam = 70;
    } else if (count === 2) {
        if (!form.ca1_label || form.ca1_label === 'Continuous Assessment') form.ca1_label = '1st CA';
        if (!form.ca2_label) form.ca2_label = '2nd CA';
        if (Number(form.default_max_ca1) === 30) form.default_max_ca1 = 15;
        if (Number(form.default_max_ca2) === 10) form.default_max_ca2 = 15;
    } else if (count === 3) {
        if (!form.ca1_label || form.ca1_label === 'Continuous Assessment') form.ca1_label = '1st CA';
        if (!form.ca2_label) form.ca2_label = '2nd CA';
        if (!form.ca3_label) form.ca3_label = '3rd CA';
        if (Number(form.default_max_ca1) === 15) form.default_max_ca1 = 10;
        if (Number(form.default_max_ca2) === 15) form.default_max_ca2 = 10;
        if (!form.default_max_ca3) form.default_max_ca3 = 10;
    }
};

// -----------------------------------------------------------------------------
// Grade Scale Modal & Actions
// -----------------------------------------------------------------------------
const isGradeModalOpen = ref(false);
const editingGrade = ref<Grade | null>(null);
const gradeForm = useForm({
    name: '',
    grade_point: 5.0,
    mark_from: 70.0,
    mark_to: 100.0,
    remark: '',
});

const openCreateGradeModal = () => {
    editingGrade.value = null;
    gradeForm.reset();
    gradeForm.clearErrors();
    gradeForm.name = '';
    gradeForm.grade_point = 0.0;
    gradeForm.mark_from = 0.0;
    gradeForm.mark_to = 100.0;
    gradeForm.remark = '';
    isGradeModalOpen.value = true;
};

const openEditGradeModal = (grade: Grade) => {
    editingGrade.value = grade;
    gradeForm.reset();
    gradeForm.clearErrors();
    gradeForm.name = grade.name;
    gradeForm.grade_point = Number(grade.grade_point);
    gradeForm.mark_from = Number(grade.mark_from);
    gradeForm.mark_to = Number(grade.mark_to);
    gradeForm.remark = grade.remark;
    isGradeModalOpen.value = true;
};

const submitGradeForm = () => {
    if (editingGrade.value) {
        gradeForm.put(updateGradeRoute(editingGrade.value.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                isGradeModalOpen.value = false;
                toast.success('Grade band updated successfully');
            },
            onError: () => toast.error('Failed to update grade band. Check fields.'),
        });
    } else {
        gradeForm.post(storeGradeRoute().url, {
            preserveScroll: true,
            onSuccess: () => {
                isGradeModalOpen.value = false;
                toast.success('Grade band created successfully');
            },
            onError: () => toast.error('Failed to create grade band. Check fields.'),
        });
    }
};

const deleteGrade = (grade: Grade) => {
    if (!confirm(`Are you sure you want to delete Grade "${grade.name}"?`)) return;
    router.delete(destroyGradeRoute(grade.id).url, {
        preserveScroll: true,
        onSuccess: () => toast.success('Grade band deleted successfully'),
        onError: () => toast.error('Failed to delete grade band'),
    });
};

// -----------------------------------------------------------------------------
// Slider Modal & Actions
// -----------------------------------------------------------------------------
const isSliderModalOpen = ref(false);
const sliderForm = useForm({
    title: '',
    subtitle: '',
    badge: '',
    primary_button_text: 'Explore Academics',
    primary_button_url: '/academics',
    secondary_button_text: 'Contact Us',
    secondary_button_url: '/contact',
    sort_order: 0,
    is_active: true,
    image: null as File | null,
});

const openNewSliderModal = () => {
    sliderForm.reset();
    isSliderModalOpen.value = true;
};

const submitSlider = () => {
    sliderForm.post('/settings/school/sliders', {
        preserveScroll: true,
        onSuccess: () => {
            isSliderModalOpen.value = false;
            sliderForm.reset();
            toast.success('Hero slider banner added');
        },
    });
};

const deleteSlider = (id: number) => {
    if (confirm('Are you sure you want to delete this hero slider?')) {
        router.delete(`/settings/school/sliders/${id}`, { preserveScroll: true });
    }
};

// -----------------------------------------------------------------------------
// Announcement Modal & Actions
// -----------------------------------------------------------------------------
const isAnnouncementModalOpen = ref(false);
const announcementForm = useForm({
    title: '',
    category: 'general',
    summary: '',
    content: '',
    is_pinned: false,
    is_published: true,
    cover_image: null as File | null,
    attachment: null as File | null,
});

const openNewAnnouncementModal = () => {
    announcementForm.reset();
    isAnnouncementModalOpen.value = true;
};

const submitAnnouncement = () => {
    announcementForm.post('/settings/school/announcements', {
        preserveScroll: true,
        onSuccess: () => {
            isAnnouncementModalOpen.value = false;
            announcementForm.reset();
            toast.success('Announcement circular published');
        },
    });
};

const deleteAnnouncement = (id: number) => {
    if (confirm('Delete this announcement circular?')) {
        router.delete(`/settings/school/announcements/${id}`, { preserveScroll: true });
    }
};

// -----------------------------------------------------------------------------
// Gallery Modal & Actions
// -----------------------------------------------------------------------------
const isGalleryModalOpen = ref(false);
const galleryForm = useForm({
    title: '',
    category: 'campus',
    caption: '',
    sort_order: 0,
    is_active: true,
    image: null as File | null,
});

const openNewGalleryModal = () => {
    galleryForm.reset();
    isGalleryModalOpen.value = true;
};

const submitGallery = () => {
    galleryForm.post('/settings/school/galleries', {
        preserveScroll: true,
        onSuccess: () => {
            isGalleryModalOpen.value = false;
            galleryForm.reset();
            toast.success('Campus photo added to gallery');
        },
    });
};

const deleteGallery = (id: number) => {
    if (confirm('Remove this photo from campus gallery?')) {
        router.delete(`/settings/school/galleries/${id}`, { preserveScroll: true });
    }
};

// -----------------------------------------------------------------------------
// FAQ Modal & Actions
// -----------------------------------------------------------------------------
const isFaqModalOpen = ref(false);
const faqForm = useForm({
    category: 'Admissions',
    question: '',
    answer: '',
    sort_order: 0,
    is_published: true,
});

const openNewFaqModal = () => {
    faqForm.reset();
    isFaqModalOpen.value = true;
};

const submitFaq = () => {
    faqForm.post('/settings/school/faqs', {
        preserveScroll: true,
        onSuccess: () => {
            isFaqModalOpen.value = false;
            faqForm.reset();
            toast.success('FAQ question added');
        },
    });
};

const deleteFaq = (id: number) => {
    if (confirm('Delete this FAQ question?')) {
        router.delete(`/settings/school/faqs/${id}`, { preserveScroll: true });
    }
};

// -----------------------------------------------------------------------------
// Inquiries Modal & Actions
// -----------------------------------------------------------------------------
const activeInquiry = ref<FrontendContactMessage | null>(null);
const inquiryStatusForm = useForm({
    status: 'unread' as 'unread' | 'read' | 'replied' | 'archived',
    admin_notes: '',
});

const openInquiryModal = (inquiry: FrontendContactMessage) => {
    activeInquiry.value = inquiry;
    inquiryStatusForm.status = inquiry.status;
    inquiryStatusForm.admin_notes = inquiry.admin_notes || '';
};

const submitInquiryStatus = () => {
    if (!activeInquiry.value) return;
    inquiryStatusForm.patch(`/settings/school/inquiries/${activeInquiry.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            activeInquiry.value = null;
            toast.success('Inquiry status updated');
        },
    });
};

const deleteInquiry = (id: number) => {
    if (confirm('Delete this inquiry message?')) {
        router.delete(`/settings/school/inquiries/${id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <div class="space-y-6">
        <Head title="Institutional Profile & CMS Administration" />

        <!-- Header -->
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <Heading
                variant="small"
                title="Institutional Profile & Website CMS"
                description="Consolidated administration for white-label school identity, public website narrative, sliders, announcements, gallery, and inquiries."
            />
            <div class="flex items-center gap-2">
                <a
                    href="/"
                    target="_blank"
                    class="text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-lg shadow-2xs inline-flex items-center gap-1.5 transition-colors"
                >
                    <Eye class="size-3.5 text-amber-500" />
                    <span>View Public Website</span>
                </a>
            </div>
        </div>

        <!-- Tab Selector Navigation -->
        <div class="flex flex-wrap items-center gap-1.5 border-b border-border pb-2">
            <button
                type="button"
                @click="activeTab = 'profile'"
                :class="[
                    activeTab === 'profile'
                        ? 'bg-primary text-primary-foreground font-bold shadow-xs'
                        : 'bg-muted/40 text-muted-foreground hover:bg-muted font-medium',
                    'inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs transition-colors'
                ]"
            >
                <Building2 class="size-4" />
                <span>Identity & Branding</span>
            </button>

            <button
                type="button"
                @click="activeTab = 'grading'"
                :class="[
                    activeTab === 'grading'
                        ? 'bg-primary text-primary-foreground font-bold shadow-xs'
                        : 'bg-muted/40 text-muted-foreground hover:bg-muted font-medium',
                    'inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs transition-colors'
                ]"
            >
                <Award class="size-4" />
                <span>Grading Scale</span>
                <span class="size-5 rounded-full bg-primary/20 text-primary-foreground text-[10px] flex items-center justify-center font-bold">
                    {{ grades.length }}
                </span>
            </button>

            <button
                type="button"
                @click="activeTab = 'cms_story'"
                :class="[
                    activeTab === 'cms_story'
                        ? 'bg-primary text-primary-foreground font-bold shadow-xs'
                        : 'bg-muted/40 text-muted-foreground hover:bg-muted font-medium',
                    'inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs transition-colors'
                ]"
            >
                <Sparkles class="size-4" />
                <span>Story & Leadership</span>
            </button>

            <button
                type="button"
                @click="activeTab = 'sliders'"
                :class="[
                    activeTab === 'sliders'
                        ? 'bg-primary text-primary-foreground font-bold shadow-xs'
                        : 'bg-muted/40 text-muted-foreground hover:bg-muted font-medium',
                    'inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs transition-colors'
                ]"
            >
                <Sliders class="size-4" />
                <span>Hero Sliders</span>
                <span class="size-5 rounded-full bg-primary/20 text-primary-foreground text-[10px] flex items-center justify-center font-bold">
                    {{ sliders.length }}
                </span>
            </button>

            <button
                type="button"
                @click="activeTab = 'announcements'"
                :class="[
                    activeTab === 'announcements'
                        ? 'bg-primary text-primary-foreground font-bold shadow-xs'
                        : 'bg-muted/40 text-muted-foreground hover:bg-muted font-medium',
                    'inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs transition-colors'
                ]"
            >
                <Megaphone class="size-4" />
                <span>Announcements</span>
                <span v-if="announcements?.data" class="size-5 rounded-full bg-primary/20 text-primary-foreground text-[10px] flex items-center justify-center font-bold">
                    {{ announcements.data.length }}
                </span>
            </button>

            <button
                type="button"
                @click="activeTab = 'gallery'"
                :class="[
                    activeTab === 'gallery'
                        ? 'bg-primary text-primary-foreground font-bold shadow-xs'
                        : 'bg-muted/40 text-muted-foreground hover:bg-muted font-medium',
                    'inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs transition-colors'
                ]"
            >
                <ImageIcon class="size-4" />
                <span>Campus Gallery</span>
                <span class="size-5 rounded-full bg-primary/20 text-primary-foreground text-[10px] flex items-center justify-center font-bold">
                    {{ galleries.length }}
                </span>
            </button>

            <button
                type="button"
                @click="activeTab = 'faqs'"
                :class="[
                    activeTab === 'faqs'
                        ? 'bg-primary text-primary-foreground font-bold shadow-xs'
                        : 'bg-muted/40 text-muted-foreground hover:bg-muted font-medium',
                    'inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs transition-colors'
                ]"
            >
                <MessageSquare class="size-4" />
                <span>FAQs</span>
                <span class="size-5 rounded-full bg-primary/20 text-primary-foreground text-[10px] flex items-center justify-center font-bold">
                    {{ faqs.length }}
                </span>
            </button>

            <button
                type="button"
                @click="activeTab = 'inquiries'"
                :class="[
                    activeTab === 'inquiries'
                        ? 'bg-primary text-primary-foreground font-bold shadow-xs'
                        : 'bg-muted/40 text-muted-foreground hover:bg-muted font-medium',
                    'inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs transition-colors'
                ]"
            >
                <Mail class="size-4" />
                <span>Inquiries Inbox</span>
                <span v-if="inquiries?.data" class="size-5 rounded-full bg-primary/20 text-primary-foreground text-[10px] flex items-center justify-center font-bold">
                    {{ inquiries.data.length }}
                </span>
            </button>
        </div>

        <!-- =================================================================== -->
        <!-- TAB 1: IDENTITY & BRANDING -->
        <!-- =================================================================== -->
        <div v-if="activeTab === 'profile'" class="space-y-6">
            <!-- Branding Live Preview -->
            <Card class="border-border/80 shadow-xs">
                <CardHeader class="pb-3 border-b border-border/60 bg-muted/20">
                    <CardTitle class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-2">
                        <Building2 class="size-4 text-primary" />
                        <span>Live Letterhead & Crest Preview</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-6">
                    <div class="flex flex-col sm:flex-row items-start justify-between gap-6 p-4 rounded-xl border border-dashed border-border bg-background">
                        <div class="flex items-center gap-4">
                            <div class="size-16 rounded-xl border border-border bg-muted/40 flex items-center justify-center overflow-hidden shrink-0">
                                <img v-if="activeLogoUrl" :src="activeLogoUrl" alt="Logo" class="size-full object-contain p-1" />
                                <Building2 v-else class="size-8 text-muted-foreground/40" />
                            </div>
                            <div>
                                <h3 class="font-extrabold text-foreground text-base sm:text-lg">
                                    {{ form.school_name || 'Institution Name' }}
                                </h3>
                                <p class="text-xs text-muted-foreground italic font-serif">
                                    "{{ form.motto || 'Motto placeholder' }}"
                                </p>
                                <span class="text-[11px] text-muted-foreground mt-1 block">
                                    {{ form.address || 'Address' }} • {{ form.phone || 'Phone' }}
                                </span>
                            </div>
                        </div>

                        <div v-if="activeStampUrl" class="border border-border p-2 rounded-lg bg-muted/10 self-center sm:self-auto text-center">
                            <img :src="activeStampUrl" alt="Principal Signature" class="h-10 object-contain mx-auto" />
                            <span class="text-[9px] uppercase tracking-wider text-muted-foreground block mt-1">Principal's Signature</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Profile Settings Form -->
            <form @submit.prevent="submitSchoolSettings" class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm font-bold">General Institutional Profile</CardTitle>
                        <CardDescription class="text-xs">Configure basic school identity, official registration codes, and contact hotlines.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="school_name" class="text-xs">Institution Name *</Label>
                                <Input id="school_name" v-model="form.school_name" :disabled="!canUpdate" required />
                                <InputError :message="form.errors.school_name" />
                            </div>

                            <div class="space-y-1.5">
                                <Label for="school_code" class="text-xs">Official School Code *</Label>
                                <Input id="school_code" v-model="form.school_code" :disabled="!canUpdate" required />
                                <InputError :message="form.errors.school_code" />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="motto" class="text-xs">Institutional Motto</Label>
                            <Input id="motto" v-model="form.motto" :disabled="!canUpdate" />
                            <InputError :message="form.errors.motto" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="space-y-1.5">
                                <Label for="phone" class="text-xs">Phone Hotline</Label>
                                <Input id="phone" v-model="form.phone" :disabled="!canUpdate" />
                                <InputError :message="form.errors.phone" />
                            </div>

                            <div class="space-y-1.5">
                                <Label for="email" class="text-xs">Official Email</Label>
                                <Input id="email" v-model="form.email" type="email" :disabled="!canUpdate" />
                                <InputError :message="form.errors.email" />
                            </div>

                            <div class="space-y-1.5">
                                <Label for="currency_symbol" class="text-xs">Currency Symbol</Label>
                                <Input id="currency_symbol" v-model="form.currency_symbol" :disabled="!canUpdate" />
                                <InputError :message="form.errors.currency_symbol" />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="address" class="text-xs">Physical Address</Label>
                            <Input id="address" v-model="form.address" :disabled="!canUpdate" />
                            <InputError :message="form.errors.address" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Continuous Assessment (CA) & Examination Structure -->
                <Card>
                    <CardHeader>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div>
                                <CardTitle class="text-sm font-bold flex items-center gap-2">
                                    <Layers class="size-4 text-primary" />
                                    <span>Continuous Assessment (CA) & Examination Scheme</span>
                                </CardTitle>
                                <CardDescription class="text-xs">
                                    Choose whether student evaluation uses 1, 2, or 3 Continuous Assessment milestones per term and specify score boundaries.
                                </CardDescription>
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge :variant="totalAssessmentWeight === 100 ? 'default' : 'outline'" class="text-xs font-mono font-semibold" :class="totalAssessmentWeight === 100 ? 'bg-emerald-600 hover:bg-emerald-600 text-white' : 'border-amber-500 text-amber-600 dark:text-amber-400'">
                                    Total Weight: {{ totalAssessmentWeight }}% {{ totalAssessmentWeight === 100 ? '✓ Balanced' : '(Expected 100%)' }}
                                </Badge>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- CA Count Selector -->
                        <div class="space-y-2">
                            <Label class="text-xs font-bold text-foreground">Assessment Milestones Per Academic Term</Label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <button
                                    type="button"
                                    :disabled="!canUpdate"
                                    @click="onCaCountSelect(1)"
                                    :class="[
                                        Number(form.ca_count) === 1
                                            ? 'border-primary bg-primary/5 ring-2 ring-primary/20 text-foreground font-semibold'
                                            : 'border-border bg-background hover:bg-muted/40 text-muted-foreground',
                                        'p-3 rounded-xl border text-left transition-all flex flex-col justify-between gap-1 text-xs'
                                    ]"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-sm">1 CA Component</span>
                                        <CheckCircle2 v-if="Number(form.ca_count) === 1" class="size-4 text-primary" />
                                    </div>
                                    <span class="text-[11px] text-muted-foreground">Single CA test + Final Exam (e.g. 30% CA + 70% Exam)</span>
                                </button>

                                <button
                                    type="button"
                                    :disabled="!canUpdate"
                                    @click="onCaCountSelect(2)"
                                    :class="[
                                        Number(form.ca_count) === 2
                                            ? 'border-primary bg-primary/5 ring-2 ring-primary/20 text-foreground font-semibold'
                                            : 'border-border bg-background hover:bg-muted/40 text-muted-foreground',
                                        'p-3 rounded-xl border text-left transition-all flex flex-col justify-between gap-1 text-xs'
                                    ]"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-sm">2 CA Components</span>
                                        <CheckCircle2 v-if="Number(form.ca_count) === 2" class="size-4 text-primary" />
                                    </div>
                                    <span class="text-[11px] text-muted-foreground">CA1 + CA2 + Final Exam (e.g. 15% + 15% + 70% Exam)</span>
                                </button>

                                <button
                                    type="button"
                                    :disabled="!canUpdate"
                                    @click="onCaCountSelect(3)"
                                    :class="[
                                        Number(form.ca_count) === 3
                                            ? 'border-primary bg-primary/5 ring-2 ring-primary/20 text-foreground font-semibold'
                                            : 'border-border bg-background hover:bg-muted/40 text-muted-foreground',
                                        'p-3 rounded-xl border text-left transition-all flex flex-col justify-between gap-1 text-xs'
                                    ]"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-sm">3 CA Components</span>
                                        <CheckCircle2 v-if="Number(form.ca_count) === 3" class="size-4 text-primary" />
                                    </div>
                                    <span class="text-[11px] text-muted-foreground">CA1 + CA2 + CA3 + Exam (e.g. 10% + 10% + 10% + 70%)</span>
                                </button>
                            </div>
                            <InputError :message="form.errors.ca_count" />
                        </div>

                        <Separator />

                        <!-- Component Label & Maximum Score Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- CA 1 -->
                            <div class="p-3 rounded-xl border border-border/80 bg-muted/20 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-foreground">Assessment 1</span>
                                    <Badge variant="secondary" class="text-[10px]">1st CA</Badge>
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="ca1_label" class="text-[11px]">Display Label *</Label>
                                    <Input id="ca1_label" v-model="form.ca1_label" :disabled="!canUpdate" placeholder="e.g. 1st CA / Test 1" class="h-8 text-xs" required />
                                    <InputError :message="form.errors.ca1_label" />
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="default_max_ca1" class="text-[11px]">Max Score (Points) *</Label>
                                    <Input id="default_max_ca1" v-model.number="form.default_max_ca1" type="number" step="0.5" min="1" max="100" :disabled="!canUpdate" class="h-8 text-xs font-semibold" required />
                                    <InputError :message="form.errors.default_max_ca1" />
                                </div>
                            </div>

                            <!-- CA 2 (Conditional) -->
                            <div v-if="Number(form.ca_count) >= 2" class="p-3 rounded-xl border border-border/80 bg-muted/20 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-foreground">Assessment 2</span>
                                    <Badge variant="secondary" class="text-[10px]">2nd CA</Badge>
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="ca2_label" class="text-[11px]">Display Label *</Label>
                                    <Input id="ca2_label" v-model="form.ca2_label" :disabled="!canUpdate" placeholder="e.g. 2nd CA / Midterm" class="h-8 text-xs" required />
                                    <InputError :message="form.errors.ca2_label" />
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="default_max_ca2" class="text-[11px]">Max Score (Points) *</Label>
                                    <Input id="default_max_ca2" v-model.number="form.default_max_ca2" type="number" step="0.5" min="0" max="100" :disabled="!canUpdate" class="h-8 text-xs font-semibold" required />
                                    <InputError :message="form.errors.default_max_ca2" />
                                </div>
                            </div>

                            <!-- CA 3 (Conditional) -->
                            <div v-if="Number(form.ca_count) >= 3" class="p-3 rounded-xl border border-border/80 bg-muted/20 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-foreground">Assessment 3</span>
                                    <Badge variant="secondary" class="text-[10px]">3rd CA</Badge>
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="ca3_label" class="text-[11px]">Display Label *</Label>
                                    <Input id="ca3_label" v-model="form.ca3_label" :disabled="!canUpdate" placeholder="e.g. 3rd CA / Assignment" class="h-8 text-xs" required />
                                    <InputError :message="form.errors.ca3_label" />
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="default_max_ca3" class="text-[11px]">Max Score (Points) *</Label>
                                    <Input id="default_max_ca3" v-model.number="form.default_max_ca3" type="number" step="0.5" min="0" max="100" :disabled="!canUpdate" class="h-8 text-xs font-semibold" required />
                                    <InputError :message="form.errors.default_max_ca3" />
                                </div>
                            </div>

                            <!-- Terminal Examination -->
                            <div class="p-3 rounded-xl border border-primary/30 bg-primary/5 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-foreground">Terminal Exam</span>
                                    <Badge variant="default" class="text-[10px]">Examination</Badge>
                                </div>
                                <div class="space-y-1.5">
                                    <Label class="text-[11px]">Component Type</Label>
                                    <Input value="Terminal Examination" disabled class="h-8 text-xs bg-muted/50" />
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="default_max_exam" class="text-[11px]">Max Score (Points) *</Label>
                                    <Input id="default_max_exam" v-model.number="form.default_max_exam" type="number" step="0.5" min="1" max="100" :disabled="!canUpdate" class="h-8 text-xs font-semibold text-primary" required />
                                    <InputError :message="form.errors.default_max_exam" />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Media Assets Upload (Logo & Stamp) -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm font-bold">Institutional Logo & Principal's Signature</CardTitle>
                        <CardDescription class="text-xs">Upload official school logo and principal's signature/stamp for report cards.</CardDescription>
                    </CardHeader>
                    <CardContent class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div class="space-y-2 border border-dashed border-border rounded-xl p-4">
                            <Label class="text-xs font-bold block">Institutional Logo / Crest (Max 2MB)</Label>
                            <input type="file" accept="image/*" @change="handleLogoChange" :disabled="!canUpdate" class="text-xs text-muted-foreground file:mr-3 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-xs file:bg-primary/10 file:text-primary hover:file:bg-primary/20" />
                            <InputError :message="form.errors.logo" />
                        </div>

                        <div class="space-y-2 border border-dashed border-border rounded-xl p-4">
                            <Label class="text-xs font-bold block">Principal's Signature / Stamp (Max 2MB)</Label>
                            <input type="file" accept="image/*" @change="handleStampChange" :disabled="!canUpdate" class="text-xs text-muted-foreground file:mr-3 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-xs file:bg-primary/10 file:text-primary hover:file:bg-primary/20" />
                            <InputError :message="form.errors.stamp" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Institutional Letterhead & Print Calibration Card -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-sm font-bold flex items-center gap-2">
                                    <FileText class="size-4 text-primary" />
                                    <span>Institutional Letterhead & Batch PDF Calibration</span>
                                </CardTitle>
                                <CardDescription class="text-xs">
                                    Upload official school letterhead image (stored directly as Base64 in database) and set default printable content margins for batch report cards.
                                </CardDescription>
                            </div>
                            <Badge v-if="letterheadPreview" variant="default" class="bg-emerald-600 text-white text-[10px]">
                                Letterhead Configured
                            </Badge>
                            <Badge v-else variant="outline" class="text-muted-foreground text-[10px]">
                                No Letterhead
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
                            <!-- Letterhead Image Upload & Preview -->
                            <div class="space-y-3">
                                <Label class="text-xs font-bold block">Letterhead Image (Max 5MB)</Label>
                                <div class="border border-dashed border-border rounded-xl p-4 text-center bg-muted/10 space-y-3">
                                    <div v-if="letterheadPreview" class="relative group mx-auto w-36 h-48 rounded-lg border border-border bg-white overflow-hidden shadow-xs">
                                        <img :src="letterheadPreview" alt="Letterhead Preview" class="w-full h-full object-contain" />
                                        <button
                                            v-if="canUpdate"
                                            type="button"
                                            @click="removeLetterhead"
                                            class="absolute top-1.5 right-1.5 size-6 rounded-full bg-destructive text-destructive-foreground flex items-center justify-center opacity-90 hover:opacity-100 shadow-sm"
                                            title="Remove Letterhead"
                                        >
                                            <X class="size-3.5" />
                                        </button>
                                    </div>
                                    <div v-else class="w-36 h-48 mx-auto rounded-lg border border-dashed border-border flex flex-col items-center justify-center text-muted-foreground p-3">
                                        <FileText class="size-8 mb-2 opacity-50" />
                                        <span class="text-[11px] font-medium">A4 Portrait Image</span>
                                        <span class="text-[9px] opacity-70">PNG, JPG, or WEBP</span>
                                    </div>

                                    <div>
                                        <input
                                            type="file"
                                            accept="image/*"
                                            @change="handleLetterheadChange"
                                            :disabled="!canUpdate"
                                            class="text-xs text-muted-foreground file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-[11px] file:bg-primary/10 file:text-primary hover:file:bg-primary/20 max-w-full"
                                        />
                                        <InputError :message="form.errors.letterhead_file" />
                                        <InputError :message="form.errors.letterhead_base64" />
                                    </div>
                                </div>
                            </div>

                            <!-- 4-Way Printable Margins Calibration -->
                            <div class="md:col-span-2 space-y-4">
                                <div class="rounded-lg border border-border bg-muted/20 p-3.5 text-xs text-muted-foreground space-y-1">
                                    <div class="font-semibold text-foreground flex items-center gap-1.5">
                                        <Info class="size-3.5 text-primary" />
                                        <span>4-Way Printable Content Margins (in points)</span>
                                    </div>
                                    <p class="text-[11px]">
                                        Define the printable area on the A4 page. Content will be offset from the page edges so student marks and tables align neatly below your letterhead header banner, above footers, and within side margins.
                                    </p>
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                    <div class="space-y-1.5">
                                        <Label for="letterhead_top_margin" class="text-xs font-semibold">Top Offset (pt) *</Label>
                                        <Input
                                            id="letterhead_top_margin"
                                            v-model.number="form.letterhead_top_margin"
                                            type="number"
                                            min="0"
                                            max="500"
                                            step="5"
                                            :disabled="!canUpdate"
                                            class="h-8 text-xs font-semibold"
                                            required
                                        />
                                        <span class="text-[10px] text-muted-foreground">Header clearance</span>
                                        <InputError :message="form.errors.letterhead_top_margin" />
                                    </div>

                                    <div class="space-y-1.5">
                                        <Label for="letterhead_bottom_margin" class="text-xs font-semibold">Bottom Offset (pt) *</Label>
                                        <Input
                                            id="letterhead_bottom_margin"
                                            v-model.number="form.letterhead_bottom_margin"
                                            type="number"
                                            min="0"
                                            max="500"
                                            step="5"
                                            :disabled="!canUpdate"
                                            class="h-8 text-xs font-semibold"
                                            required
                                        />
                                        <span class="text-[10px] text-muted-foreground">Footer clearance</span>
                                        <InputError :message="form.errors.letterhead_bottom_margin" />
                                    </div>

                                    <div class="space-y-1.5">
                                        <Label for="letterhead_left_margin" class="text-xs font-semibold">Left Margin (pt) *</Label>
                                        <Input
                                            id="letterhead_left_margin"
                                            v-model.number="form.letterhead_left_margin"
                                            type="number"
                                            min="0"
                                            max="500"
                                            step="5"
                                            :disabled="!canUpdate"
                                            class="h-8 text-xs font-semibold"
                                            required
                                        />
                                        <span class="text-[10px] text-muted-foreground">Left border margin</span>
                                        <InputError :message="form.errors.letterhead_left_margin" />
                                    </div>

                                    <div class="space-y-1.5">
                                        <Label for="letterhead_right_margin" class="text-xs font-semibold">Right Margin (pt) *</Label>
                                        <Input
                                            id="letterhead_right_margin"
                                            v-model.number="form.letterhead_right_margin"
                                            type="number"
                                            min="0"
                                            max="500"
                                            step="5"
                                            :disabled="!canUpdate"
                                            class="h-8 text-xs font-semibold"
                                            required
                                        />
                                        <span class="text-[10px] text-muted-foreground">Right border margin</span>
                                        <InputError :message="form.errors.letterhead_right_margin" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                    <div v-if="canUpdate" class="p-6 pt-0 flex justify-end">
                        <Button type="submit" :disabled="form.processing" class="text-xs">
                            <Spinner v-if="form.processing" class="size-4 mr-2" />
                            <span>Save Profile & Branding</span>
                        </Button>
                    </div>
                </Card>
            </form>
        </div>

        <!-- =================================================================== -->
        <!-- TAB: GRADING SCALE -->
        <!-- =================================================================== -->
        <div v-if="activeTab === 'grading'" class="space-y-6">
            <Card class="border-border/80 shadow-xs">
                <CardHeader class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-border/60">
                    <div>
                        <CardTitle class="text-base font-bold flex items-center gap-2">
                            <Award class="size-5 text-primary" />
                            <span>Academic Grading Scale & Performance Bands</span>
                        </CardTitle>
                        <CardDescription class="text-xs">
                            Define grade letters, GPA points, mark thresholds (%), and qualitative remarks used across Marks Entry, Broadsheet, and Report Cards.
                        </CardDescription>
                    </div>
                    <Button v-if="canUpdate" size="sm" @click="openCreateGradeModal" class="gap-1.5 text-xs font-semibold shrink-0">
                        <Plus class="size-4" />
                        <span>Add Grade Band</span>
                    </Button>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-muted/40 border-b border-border/60 uppercase font-semibold text-muted-foreground tracking-wider">
                                <tr>
                                    <th class="px-6 py-3.5">Grade Letter</th>
                                    <th class="px-6 py-3.5">Score Range (%)</th>
                                    <th class="px-6 py-3.5">Grade Point (GPA)</th>
                                    <th class="px-6 py-3.5">Performance Remark</th>
                                    <th v-if="canUpdate" class="px-6 py-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/40">
                                <tr v-for="grade in grades" :key="grade.id" class="hover:bg-muted/20 transition-colors">
                                    <td class="px-6 py-4">
                                        <Badge
                                            variant="outline"
                                            class="text-sm font-bold font-mono px-2.5 py-0.5"
                                            :class="{
                                                'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-300': grade.name.startsWith('A') || grade.name.startsWith('B'),
                                                'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-300': grade.name.startsWith('C'),
                                                'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-300': grade.name.startsWith('D') || grade.name.startsWith('E'),
                                                'bg-destructive/10 text-destructive border-destructive/30': grade.name.startsWith('F'),
                                            }"
                                        >
                                            {{ grade.name }}
                                        </Badge>
                                    </td>
                                    <td class="px-6 py-4 font-mono font-medium text-foreground">
                                        {{ Number(grade.mark_from) }}% - {{ Number(grade.mark_to) }}%
                                    </td>
                                    <td class="px-6 py-4 font-mono font-bold text-foreground">
                                        {{ Number(grade.grade_point).toFixed(2) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-medium text-foreground">{{ grade.remark }}</span>
                                    </td>
                                    <td v-if="canUpdate" class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <Button variant="ghost" size="icon" class="size-8" @click="openEditGradeModal(grade)" title="Edit Grade Band">
                                                <Pencil class="size-3.5" />
                                            </Button>
                                            <Button variant="ghost" size="icon" class="size-8 text-destructive hover:text-destructive" @click="deleteGrade(grade)" title="Delete Grade Band">
                                                <Trash2 class="size-3.5" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="grades.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-muted-foreground">
                                        <Award class="size-8 mx-auto text-muted-foreground/60 mb-2" />
                                        <p class="font-medium">No grading scale bands defined.</p>
                                        <p class="text-xs mt-1">Click "Add Grade Band" above to configure your institution's assessment scale.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- =================================================================== -->
        <!-- TAB 2: STORY & LEADERSHIP -->
        <!-- =================================================================== -->
        <div v-if="activeTab === 'cms_story'" class="space-y-6">
            <form @submit.prevent="submitSchoolSettings" class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm font-bold">Public Institutional Narrative</CardTitle>
                        <CardDescription class="text-xs">Narrative shown on the public About page and landing highlights.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-1.5">
                            <Label for="about_text" class="text-xs font-bold">About Our School Narrative</Label>
                            <textarea
                                id="about_text"
                                v-model="form.about_text"
                                rows="4"
                                :disabled="!canUpdate"
                                placeholder="Narrate the history, founding vision, and academic achievements..."
                                class="w-full bg-background text-foreground text-xs p-3 rounded-lg border border-input focus:outline-hidden focus:ring-1 focus:ring-ring"
                            />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="mission" class="text-xs font-bold">Institutional Mission</Label>
                                <textarea
                                    id="mission"
                                    v-model="form.mission"
                                    rows="3"
                                    :disabled="!canUpdate"
                                    class="w-full bg-background text-foreground text-xs p-3 rounded-lg border border-input focus:outline-hidden"
                                />
                            </div>

                            <div class="space-y-1.5">
                                <Label for="vision" class="text-xs font-bold">Institutional Vision</Label>
                                <textarea
                                    id="vision"
                                    v-model="form.vision"
                                    rows="3"
                                    :disabled="!canUpdate"
                                    class="w-full bg-background text-foreground text-xs p-3 rounded-lg border border-input focus:outline-hidden"
                                />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="core_values_input" class="text-xs font-bold">Core Values (Comma Separated)</Label>
                            <Input
                                id="core_values_input"
                                v-model="coreValuesInput"
                                :disabled="!canUpdate"
                                placeholder="e.g. Academic Excellence, Discipline, Scientific Innovation, Integrity"
                            />
                            <p class="text-[11px] text-muted-foreground">Each comma-separated item will render as an institutional badge.</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Leadership & Office Hours -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm font-bold">Head of School & Public Hours</CardTitle>
                        <CardDescription class="text-xs">Principal's welcome message, portrait upload, and public visiting hours.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="principal_name" class="text-xs">Principal / Head of School Name</Label>
                                <Input id="principal_name" v-model="form.principal_name" :disabled="!canUpdate" />
                            </div>

                            <div class="space-y-1.5">
                                <Label for="office_hours" class="text-xs">Administrative & Visiting Hours</Label>
                                <Input id="office_hours" v-model="form.office_hours" :disabled="!canUpdate" placeholder="Mon - Fri: 8:00 AM - 3:30 PM" />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="principal_message" class="text-xs font-bold">Principal's Welcoming Quote / Address</Label>
                            <textarea
                                id="principal_message"
                                v-model="form.principal_message"
                                rows="3"
                                :disabled="!canUpdate"
                                placeholder="Welcoming message displayed on the Home and About pages..."
                                class="w-full bg-background text-foreground text-xs p-3 rounded-lg border border-input focus:outline-hidden"
                            />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-2">
                            <div class="border border-dashed border-border rounded-xl p-4 space-y-2">
                                <Label class="text-xs font-bold block">Principal Photo / Portrait (Max 2MB)</Label>
                                <div v-if="activePrincipalPhotoUrl" class="size-16 rounded-lg overflow-hidden border border-border mb-2">
                                    <img :src="activePrincipalPhotoUrl" alt="Principal" class="size-full object-cover" />
                                </div>
                                <input type="file" accept="image/*" @change="handlePrincipalPhotoChange" :disabled="!canUpdate" class="text-xs text-muted-foreground file:mr-3 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-xs file:bg-primary/10 file:text-primary" />
                            </div>

                            <div class="border border-dashed border-border rounded-xl p-4 space-y-2">
                                <Label class="text-xs font-bold block">Admissions Prospectus (PDF, Max 10MB)</Label>
                                <a v-if="props.settings.prospectus_url" :href="props.settings.prospectus_url" target="_blank" class="inline-flex items-center gap-1.5 text-xs text-primary font-bold hover:underline mb-2">
                                    <Download class="size-3.5" />
                                    <span>Current Uploaded Prospectus</span>
                                </a>
                                <input type="file" accept=".pdf" @change="handleProspectusChange" :disabled="!canUpdate" class="text-xs text-muted-foreground file:mr-3 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-xs file:bg-primary/10 file:text-primary" />
                            </div>
                        </div>

                        <!-- Social Media & Maps -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                            <div class="space-y-1.5">
                                <Label class="text-xs">Facebook URL</Label>
                                <Input v-model="socialFb" placeholder="https://facebook.com/..." :disabled="!canUpdate" />
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs">X / Twitter URL</Label>
                                <Input v-model="socialTw" placeholder="https://x.com/..." :disabled="!canUpdate" />
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs">Instagram URL</Label>
                                <Input v-model="socialInsta" placeholder="https://instagram.com/..." :disabled="!canUpdate" />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="map_embed_url" class="text-xs">Google Maps / OpenStreetMap Embed URL</Label>
                            <Input id="map_embed_url" v-model="form.map_embed_url" placeholder="https://www.google.com/maps/embed?..." :disabled="!canUpdate" />
                        </div>
                    </CardContent>
                    <div v-if="canUpdate" class="p-6 pt-0 flex justify-end">
                        <Button type="submit" :disabled="form.processing" class="text-xs">
                            <Spinner v-if="form.processing" class="size-4 mr-2" />
                            <span>Save Story & Leadership</span>
                        </Button>
                    </div>
                </Card>
            </form>
        </div>

        <!-- =================================================================== -->
        <!-- TAB 3: HERO SLIDERS -->
        <!-- =================================================================== -->
        <div v-if="activeTab === 'sliders'" class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-foreground">Landing Page Hero Visuals</h3>
                    <p class="text-xs text-muted-foreground">Manage animated hero slides shown on the public homepage.</p>
                </div>
                <Button v-if="canUpdate" type="button" @click="openNewSliderModal" size="sm" class="text-xs gap-1.5">
                    <Plus class="size-3.5" />
                    <span>Add Hero Slide</span>
                </Button>
            </div>

            <div v-if="sliders.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Card v-for="slide in sliders" :key="slide.id" class="overflow-hidden border-border/80">
                    <div class="aspect-16/7 bg-muted relative">
                        <img v-if="slide.image_url" :src="slide.image_url" :alt="slide.title" class="size-full object-cover" />
                        <div v-else class="size-full flex items-center justify-center text-muted-foreground/40 bg-slate-900 text-white font-bold text-xs">
                            Default Gradient Banner
                        </div>
                        <div class="absolute top-2 right-2 flex items-center gap-1.5">
                            <Badge v-if="slide.is_active" variant="default" class="text-[10px]">Active</Badge>
                            <Badge v-else variant="secondary" class="text-[10px]">Draft</Badge>
                        </div>
                    </div>
                    <CardContent class="p-4 space-y-2">
                        <span v-if="slide.badge" class="text-[10px] font-bold text-amber-600 uppercase">{{ slide.badge }}</span>
                        <h4 class="font-bold text-sm text-foreground line-clamp-1">{{ slide.title }}</h4>
                        <p v-if="slide.subtitle" class="text-xs text-muted-foreground line-clamp-2">{{ slide.subtitle }}</p>

                        <div class="flex items-center justify-between pt-2 border-t border-border text-[11px] text-muted-foreground">
                            <span>Order: {{ slide.sort_order }}</span>
                            <Button v-if="canUpdate" variant="ghost" size="sm" @click="deleteSlider(slide.id)" class="text-destructive hover:bg-destructive/10 h-7 px-2">
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div v-else class="text-center py-12 border border-dashed border-border rounded-2xl bg-card space-y-2">
                <Sliders class="size-8 text-muted-foreground/40 mx-auto" />
                <p class="text-xs text-muted-foreground font-medium">No hero slider visuals created yet.</p>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- TAB 4: ANNOUNCEMENTS -->
        <!-- =================================================================== -->
        <div v-if="activeTab === 'announcements'" class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-foreground">Public Circulars & Announcements</h3>
                    <p class="text-xs text-muted-foreground">Publish academic notices, examination schedules, and PDF attachments.</p>
                </div>
                <Button v-if="canUpdate" type="button" @click="openNewAnnouncementModal" size="sm" class="text-xs gap-1.5">
                    <Plus class="size-3.5" />
                    <span>New Announcement</span>
                </Button>
            </div>

            <div v-if="announcements?.data && announcements.data.length > 0" class="border border-border rounded-xl overflow-hidden bg-card">
                <table class="w-full text-left text-xs">
                    <thead class="bg-muted/40 border-b border-border text-muted-foreground font-bold uppercase text-[10px]">
                        <tr>
                            <th class="p-3">Title</th>
                            <th class="p-3">Category</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Date</th>
                            <th class="p-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="item in announcements.data" :key="item.id" class="hover:bg-muted/20">
                            <td class="p-3">
                                <div class="font-bold text-foreground">{{ item.title }}</div>
                                <div class="text-[11px] text-muted-foreground truncate max-w-xs">{{ item.summary || item.content }}</div>
                            </td>
                            <td class="p-3 capitalize font-medium">{{ item.category }}</td>
                            <td class="p-3">
                                <Badge v-if="item.is_pinned" class="bg-amber-500 text-slate-950 text-[10px] mr-1">Pinned</Badge>
                                <Badge v-if="item.is_published" variant="outline" class="text-[10px]">Published</Badge>
                                <Badge v-else variant="secondary" class="text-[10px]">Draft</Badge>
                            </td>
                            <td class="p-3 text-muted-foreground">{{ item.published_at ? new Date(item.published_at).toLocaleDateString() : 'Draft' }}</td>
                            <td class="p-3 text-right">
                                <Button v-if="canUpdate" variant="ghost" size="sm" @click="deleteAnnouncement(item.id)" class="text-destructive hover:bg-destructive/10 h-7 px-2">
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="text-center py-12 border border-dashed border-border rounded-2xl bg-card space-y-2">
                <Megaphone class="size-8 text-muted-foreground/40 mx-auto" />
                <p class="text-xs text-muted-foreground font-medium">No announcements published yet.</p>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- TAB 5: CAMPUS GALLERY -->
        <!-- =================================================================== -->
        <div v-if="activeTab === 'gallery'" class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-foreground">Campus Life Photo Media</h3>
                    <p class="text-xs text-muted-foreground">Upload and categorize campus laboratories, sports, and cultural events.</p>
                </div>
                <Button v-if="canUpdate" type="button" @click="openNewGalleryModal" size="sm" class="text-xs gap-1.5">
                    <Plus class="size-3.5" />
                    <span>Upload Photo</span>
                </Button>
            </div>

            <div v-if="galleries.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <div v-for="photo in galleries" :key="photo.id" class="group relative aspect-square rounded-xl overflow-hidden border border-border bg-muted">
                    <img :src="photo.image_url || photo.image_path" :alt="photo.title" class="size-full object-cover" />
                    <div class="absolute inset-0 bg-slate-950/70 opacity-0 group-hover:opacity-100 transition-opacity p-2 flex flex-col justify-between">
                        <span class="text-[9px] uppercase tracking-wider text-amber-400 font-bold">{{ photo.category }}</span>
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] text-white truncate max-w-24">{{ photo.title }}</span>
                            <button v-if="canUpdate" type="button" @click="deleteGallery(photo.id)" class="text-red-400 hover:text-red-300">
                                <Trash2 class="size-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="text-center py-12 border border-dashed border-border rounded-2xl bg-card space-y-2">
                <ImageIcon class="size-8 text-muted-foreground/40 mx-auto" />
                <p class="text-xs text-muted-foreground font-medium">No campus photos uploaded yet.</p>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- TAB 6: FAQS -->
        <!-- =================================================================== -->
        <div v-if="activeTab === 'faqs'" class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-foreground">Frequently Asked Questions (FAQ)</h3>
                    <p class="text-xs text-muted-foreground">Manage public questions and answers for admissions, academics, and portal support.</p>
                </div>
                <Button v-if="canUpdate" type="button" @click="openNewFaqModal" size="sm" class="text-xs gap-1.5">
                    <Plus class="size-3.5" />
                    <span>Add Question</span>
                </Button>
            </div>

            <div v-if="faqs.length > 0" class="space-y-3">
                <Card v-for="faq in faqs" :key="faq.id" class="p-4 flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <Badge variant="outline" class="text-[10px] font-bold">{{ faq.category }}</Badge>
                            <h4 class="font-bold text-xs sm:text-sm text-foreground">{{ faq.question }}</h4>
                        </div>
                        <p class="text-xs text-muted-foreground whitespace-pre-line pl-1">{{ faq.answer }}</p>
                    </div>
                    <Button v-if="canUpdate" variant="ghost" size="sm" @click="deleteFaq(faq.id)" class="text-destructive hover:bg-destructive/10 h-7 px-2 shrink-0">
                        <Trash2 class="size-3.5" />
                    </Button>
                </Card>
            </div>

            <div v-else class="text-center py-12 border border-dashed border-border rounded-2xl bg-card space-y-2">
                <MessageSquare class="size-8 text-muted-foreground/40 mx-auto" />
                <p class="text-xs text-muted-foreground font-medium">No FAQs created yet.</p>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- TAB 7: INQUIRIES INBOX -->
        <!-- =================================================================== -->
        <div v-if="activeTab === 'inquiries'" class="space-y-6">
            <div>
                <h3 class="text-sm font-bold text-foreground">Public Contact Form Submissions</h3>
                <p class="text-xs text-muted-foreground">Manage incoming inquiries from prospective parents and visitors.</p>
            </div>

            <div v-if="inquiries?.data && inquiries.data.length > 0" class="border border-border rounded-xl overflow-hidden bg-card">
                <table class="w-full text-left text-xs">
                    <thead class="bg-muted/40 border-b border-border text-muted-foreground font-bold uppercase text-[10px]">
                        <tr>
                            <th class="p-3">Sender</th>
                            <th class="p-3">Subject</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Date</th>
                            <th class="p-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="inquiry in inquiries.data" :key="inquiry.id" class="hover:bg-muted/20 cursor-pointer" @click="openInquiryModal(inquiry)">
                            <td class="p-3">
                                <div class="font-bold text-foreground">{{ inquiry.name }}</div>
                                <div class="text-[11px] text-muted-foreground">{{ inquiry.email }} • {{ inquiry.phone || 'No phone' }}</div>
                            </td>
                            <td class="p-3">
                                <div class="font-medium text-foreground">{{ inquiry.subject }}</div>
                                <div class="text-[11px] text-muted-foreground line-clamp-1 max-w-xs">{{ inquiry.message }}</div>
                            </td>
                            <td class="p-3">
                                <Badge
                                    :variant="inquiry.status === 'unread' ? 'destructive' : inquiry.status === 'replied' ? 'default' : 'secondary'"
                                    class="text-[10px] capitalize"
                                >
                                    {{ inquiry.status }}
                                </Badge>
                            </td>
                            <td class="p-3 text-muted-foreground text-[11px]">
                                {{ new Date(inquiry.created_at).toLocaleDateString() }}
                            </td>
                            <td class="p-3 text-right" @click.stop>
                                <Button v-if="canUpdate" variant="ghost" size="sm" @click="deleteInquiry(inquiry.id)" class="text-destructive hover:bg-destructive/10 h-7 px-2">
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="text-center py-12 border border-dashed border-border rounded-2xl bg-card space-y-2">
                <Mail class="size-8 text-muted-foreground/40 mx-auto" />
                <p class="text-xs text-muted-foreground font-medium">No contact inquiries received yet.</p>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- MODALS -->
        <!-- =================================================================== -->

        <!-- Slider Modal -->
        <Dialog :open="isSliderModalOpen" @update:open="isSliderModalOpen = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="text-sm font-bold">New Hero Slide</DialogTitle>
                    <DialogDescription class="text-xs">Add an eye-catching banner slide for the homepage.</DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitSlider" class="space-y-3 pt-2">
                    <div class="space-y-1">
                        <Label class="text-xs">Headline Title *</Label>
                        <Input v-model="sliderForm.title" required />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs">Sub-headline / Summary</Label>
                        <Input v-model="sliderForm.subtitle" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label class="text-xs">Badge Tag</Label>
                            <Input v-model="sliderForm.badge" placeholder="e.g. Admissions Open" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs">Sort Order</Label>
                            <Input v-model.number="sliderForm.sort_order" type="number" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label class="text-xs">Primary Button Text</Label>
                            <Input v-model="sliderForm.primary_button_text" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs">Primary Button URL</Label>
                            <Input v-model="sliderForm.primary_button_url" />
                        </div>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs">Slide Background Image (Optional)</Label>
                        <input type="file" accept="image/*" @change="sliderForm.image = ($event.target as HTMLInputElement).files?.[0] || null" class="text-xs text-muted-foreground" />
                    </div>
                    <DialogFooter class="pt-4">
                        <Button type="button" variant="outline" @click="isSliderModalOpen = false" class="text-xs">Cancel</Button>
                        <Button type="submit" :disabled="sliderForm.processing" class="text-xs">Save Slide</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Announcement Modal -->
        <Dialog :open="isAnnouncementModalOpen" @update:open="isAnnouncementModalOpen = $event">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle class="text-sm font-bold">Publish Official Notice</DialogTitle>
                    <DialogDescription class="text-xs">Create a new circular for students, guardians, and visitors.</DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitAnnouncement" class="space-y-3 pt-2">
                    <div class="space-y-1">
                        <Label class="text-xs">Notice Title *</Label>
                        <Input v-model="announcementForm.title" required />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label class="text-xs">Category *</Label>
                            <select v-model="announcementForm.category" class="w-full bg-background border border-input rounded-md px-3 py-1.5 text-xs">
                                <option value="general">General</option>
                                <option value="academic">Academic</option>
                                <option value="exam">Examination</option>
                                <option value="sports">Sports & Cultural</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-4 pt-4">
                            <label class="flex items-center gap-1.5 text-xs font-medium cursor-pointer">
                                <input type="checkbox" v-model="announcementForm.is_pinned" class="rounded" />
                                <span>Pin Notice</span>
                            </label>
                            <label class="flex items-center gap-1.5 text-xs font-medium cursor-pointer">
                                <input type="checkbox" v-model="announcementForm.is_published" class="rounded" />
                                <span>Publish Live</span>
                            </label>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs">Short Summary</Label>
                        <Input v-model="announcementForm.summary" placeholder="Brief 1-sentence teaser..." />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs">Content / Circular Body *</Label>
                        <textarea v-model="announcementForm.content" rows="4" required class="w-full bg-background border border-input rounded-md p-2 text-xs" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label class="text-xs">Cover Image</Label>
                            <input type="file" accept="image/*" @change="announcementForm.cover_image = ($event.target as HTMLInputElement).files?.[0] || null" class="text-xs" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs">Document Attachment (PDF)</Label>
                            <input type="file" accept=".pdf,.doc,.docx" @change="announcementForm.attachment = ($event.target as HTMLInputElement).files?.[0] || null" class="text-xs" />
                        </div>
                    </div>
                    <DialogFooter class="pt-4">
                        <Button type="button" variant="outline" @click="isAnnouncementModalOpen = false" class="text-xs">Cancel</Button>
                        <Button type="submit" :disabled="announcementForm.processing" class="text-xs">Publish Circular</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Gallery Modal -->
        <Dialog :open="isGalleryModalOpen" @update:open="isGalleryModalOpen = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-sm font-bold">Add Campus Photo</DialogTitle>
                    <DialogDescription class="text-xs">Upload photos to the public gallery.</DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitGallery" class="space-y-3 pt-2">
                    <div class="space-y-1">
                        <Label class="text-xs">Photo Title *</Label>
                        <Input v-model="galleryForm.title" required />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs">Category *</Label>
                        <select v-model="galleryForm.category" class="w-full bg-background border border-input rounded-md px-3 py-1.5 text-xs">
                            <option value="campus">Campus & Buildings</option>
                            <option value="science_labs">Science & Computer Labs</option>
                            <option value="sports">Sports & Athletics</option>
                            <option value="cultural">Cultural Day & Events</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs">Caption (Optional)</Label>
                        <Input v-model="galleryForm.caption" />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs">Image File *</Label>
                        <input type="file" accept="image/*" required @change="galleryForm.image = ($event.target as HTMLInputElement).files?.[0] || null" class="text-xs" />
                    </div>
                    <DialogFooter class="pt-4">
                        <Button type="button" variant="outline" @click="isGalleryModalOpen = false" class="text-xs">Cancel</Button>
                        <Button type="submit" :disabled="galleryForm.processing" class="text-xs">Upload Photo</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- FAQ Modal -->
        <Dialog :open="isFaqModalOpen" @update:open="isFaqModalOpen = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-sm font-bold">Add FAQ Question</DialogTitle>
                    <DialogDescription class="text-xs">Add common question and answer.</DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitFaq" class="space-y-3 pt-2">
                    <div class="space-y-1">
                        <Label class="text-xs">Category</Label>
                        <select v-model="faqForm.category" class="w-full bg-background border border-input rounded-md px-3 py-1.5 text-xs">
                            <option value="Admissions">Admissions</option>
                            <option value="Academics">Academics</option>
                            <option value="Portal">Portal & Examinations</option>
                            <option value="General">General</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs">Question *</Label>
                        <Input v-model="faqForm.question" required />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs">Answer *</Label>
                        <textarea v-model="faqForm.answer" rows="3" required class="w-full bg-background border border-input rounded-md p-2 text-xs" />
                    </div>
                    <DialogFooter class="pt-4">
                        <Button type="button" variant="outline" @click="isFaqModalOpen = false" class="text-xs">Cancel</Button>
                        <Button type="submit" :disabled="faqForm.processing" class="text-xs">Save Question</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Inquiry View / Manage Modal -->
        <Dialog :open="activeInquiry !== null" @update:open="activeInquiry = null">
            <DialogContent v-if="activeInquiry" class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="text-sm font-bold">{{ activeInquiry.subject }}</DialogTitle>
                    <DialogDescription class="text-xs">
                        From {{ activeInquiry.name }} ({{ activeInquiry.email }}) • {{ activeInquiry.phone || 'No phone' }}
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 pt-2">
                    <div class="p-4 bg-muted/30 rounded-xl border border-border text-xs leading-relaxed whitespace-pre-line text-foreground">
                        {{ activeInquiry.message }}
                    </div>

                    <form @submit.prevent="submitInquiryStatus" class="space-y-3">
                        <div class="space-y-1">
                            <Label class="text-xs font-bold">Inquiry Status</Label>
                            <select v-model="inquiryStatusForm.status" class="w-full bg-background border border-input rounded-md px-3 py-1.5 text-xs">
                                <option value="unread">Unread</option>
                                <option value="read">Mark as Read</option>
                                <option value="replied">Replied</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <Label class="text-xs font-bold">Internal Admin Notes</Label>
                            <textarea
                                v-model="inquiryStatusForm.admin_notes"
                                rows="3"
                                placeholder="Record notes about phone calls, responses, or follow-ups..."
                                class="w-full bg-background border border-input rounded-md p-2 text-xs"
                            />
                        </div>

                        <DialogFooter class="pt-4">
                            <Button type="button" variant="outline" @click="activeInquiry = null" class="text-xs">Close</Button>
                            <Button type="submit" :disabled="inquiryStatusForm.processing" class="text-xs">Save Status</Button>
                        </DialogFooter>
                    </form>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Grade Band Dialog (Create/Edit) -->
        <Dialog :open="isGradeModalOpen" @update:open="(val) => (isGradeModalOpen = val)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">
                        {{ editingGrade ? 'Edit Grade Band' : 'Add New Grade Band' }}
                    </DialogTitle>
                    <DialogDescription class="text-xs">
                        Configure grade letter, GPA points, numeric score thresholds, and qualitative report card remark.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitGradeForm" class="space-y-4 py-2">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label for="grade_name" class="text-xs font-semibold">Grade Letter *</Label>
                            <Input id="grade_name" v-model="gradeForm.name" placeholder="e.g. A, A1, B" class="h-9 font-bold" required />
                            <InputError :message="gradeForm.errors.name" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="grade_point" class="text-xs font-semibold">Grade Point (GPA) *</Label>
                            <Input id="grade_point" v-model.number="gradeForm.grade_point" type="number" step="0.01" min="0" max="10" placeholder="5.00" class="h-9 font-semibold" required />
                            <InputError :message="gradeForm.errors.grade_point" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label for="mark_from" class="text-xs font-semibold">Min Score (%) *</Label>
                            <Input id="mark_from" v-model.number="gradeForm.mark_from" type="number" step="0.01" min="0" max="100" placeholder="70" class="h-9 font-mono" required />
                            <InputError :message="gradeForm.errors.mark_from" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="mark_to" class="text-xs font-semibold">Max Score (%) *</Label>
                            <Input id="mark_to" v-model.number="gradeForm.mark_to" type="number" step="0.01" min="0" max="100" placeholder="100" class="h-9 font-mono" required />
                            <InputError :message="gradeForm.errors.mark_to" />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="grade_remark" class="text-xs font-semibold">Performance Remark *</Label>
                        <Input id="grade_remark" v-model="gradeForm.remark" placeholder="e.g. Excellent, Very Good, Credit, Pass" class="h-9 text-xs" required />
                        <InputError :message="gradeForm.errors.remark" />
                    </div>

                    <DialogFooter class="pt-4">
                        <Button type="button" variant="outline" size="sm" @click="isGradeModalOpen = false">
                            Cancel
                        </Button>
                        <Button type="submit" size="sm" :disabled="gradeForm.processing" class="gap-1.5">
                            <Spinner v-if="gradeForm.processing" class="size-4" />
                            <span>{{ editingGrade ? 'Update Grade Band' : 'Create Grade Band' }}</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
