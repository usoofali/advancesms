<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Briefcase,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Edit2,
    GraduationCap,
    HeartHandshake,
    Home,
    KeyRound,
    Mail,
    MapPin,
    Phone,
    Plus,
    Search,
    ShieldCheck,
    Trash2,
    UserCheck,
    Users,
    XCircle,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { destroy as destroyGuardian, index as peopleGuardiansIndex, store as storeGuardian, update as updateGuardian } from '@/routes/people/guardians';
import type { Guardian, GuardianStats } from '@/types/people';

interface Props {
    guardians: Guardian[];
    stats: GuardianStats;
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Guardians Directory',
                href: peopleGuardiansIndex().url,
            },
        ],
    },
});

const page = usePage();

// Permissions
const userPermissions = computed(() => (page.props.auth?.permissions as string[]) ?? []);
const userRoles = computed(() => (page.props.auth?.roles as string[]) ?? []);
const isSuperAdmin = computed(() => userPermissions.value.includes('*') || userRoles.value.includes('superadmin'));

const canCreateGuardian = computed(() => isSuperAdmin.value || userPermissions.value.includes('guardians.create'));
const canUpdateGuardian = computed(() => isSuperAdmin.value || userPermissions.value.includes('guardians.update'));
const canDeleteGuardian = computed(() => isSuperAdmin.value || userPermissions.value.includes('guardians.delete'));

// =========================================================================
// SEARCH & FILTERS
// =========================================================================
const searchQuery = ref('');
const portalFilter = ref<string>('all');

const filteredGuardians = computed(() => {
    return props.guardians.filter((guardian) => {
        const query = searchQuery.value.toLowerCase().trim();
        const matchesSearch =
            !query ||
            guardian.name.toLowerCase().includes(query) ||
            guardian.phone.toLowerCase().includes(query) ||
            (guardian.email && guardian.email.toLowerCase().includes(query)) ||
            (guardian.occupation && guardian.occupation.toLowerCase().includes(query)) ||
            guardian.relationship.toLowerCase().includes(query) ||
            guardian.students?.some((s) => s.user?.name.toLowerCase().includes(query) || s.admission_number.toLowerCase().includes(query));

        const matchesPortal =
            portalFilter.value === 'all' ||
            (portalFilter.value === 'has_portal' && guardian.user_id !== null) ||
            (portalFilter.value === 'no_portal' && guardian.user_id === null);

        return matchesSearch && matchesPortal;
    });
});

// =========================================================================
// PAGINATION (15 items per page)
// =========================================================================
const currentPage = ref(1);
const itemsPerPage = ref(15);

watch([searchQuery, portalFilter], () => {
    currentPage.value = 1;
});

const totalPages = computed(() => {
    return Math.ceil(filteredGuardians.value.length / itemsPerPage.value) || 1;
});

const paginatedGuardians = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    return filteredGuardians.value.slice(start, start + itemsPerPage.value);
});

const paginationStart = computed(() => {
    if (filteredGuardians.value.length === 0) return 0;
    return (currentPage.value - 1) * itemsPerPage.value + 1;
});

const paginationEnd = computed(() => {
    return Math.min(currentPage.value * itemsPerPage.value, filteredGuardians.value.length);
});

const displayedPageNumbers = computed(() => {
    const pages: number[] = [];
    const maxButtons = 5;
    let start = Math.max(1, currentPage.value - Math.floor(maxButtons / 2));
    let end = Math.min(totalPages.value, start + maxButtons - 1);

    if (end - start + 1 < maxButtons) {
        start = Math.max(1, end - maxButtons + 1);
    }

    for (let i = start; i <= end; i++) {
        pages.push(i);
    }
    return pages;
});

// =========================================================================
// GUARDIAN CREATE & EDIT MODAL
// =========================================================================
const isGuardianModalOpen = ref(false);
const editingGuardian = ref<Guardian | null>(null);

const guardianForm = useForm({
    name: '',
    relationship: 'Parent',
    phone: '',
    email: '',
    occupation: '',
    address: '',
    create_user_account: false,
    username: '',
    password: '',
});

const relationshipOptions = [
    'Parent',
    'Father',
    'Mother',
    'Guardian',
    'Uncle',
    'Aunt',
    'Grandparent',
    'Brother',
    'Sister',
    'Sponsor',
    'Other',
];

const openCreateGuardianModal = () => {
    editingGuardian.value = null;
    guardianForm.reset();
    guardianForm.clearErrors();
    guardianForm.relationship = 'Parent';
    guardianForm.create_user_account = false;
    guardianForm.username = '';
    guardianForm.password = '';
    isGuardianModalOpen.value = true;
};

const openEditGuardianModal = (guardian: Guardian) => {
    editingGuardian.value = guardian;
    guardianForm.reset();
    guardianForm.clearErrors();
    guardianForm.name = guardian.name;
    guardianForm.relationship = guardian.relationship;
    guardianForm.phone = guardian.phone;
    guardianForm.email = guardian.email ?? '';
    guardianForm.occupation = guardian.occupation ?? '';
    guardianForm.address = guardian.address ?? '';
    guardianForm.create_user_account = guardian.user_id !== null;
    guardianForm.username = guardian.user?.username ?? '';
    guardianForm.password = '';
    isGuardianModalOpen.value = true;
};

const submitGuardianForm = () => {
    if (editingGuardian.value) {
        guardianForm.put(updateGuardian(editingGuardian.value.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                isGuardianModalOpen.value = false;
                toast.success('Guardian profile updated successfully.');
            },
            onError: () => {
                toast.error('Failed to update guardian profile. Please check the form.');
            },
        });
    } else {
        guardianForm.post(storeGuardian().url, {
            preserveScroll: true,
            onSuccess: () => {
                isGuardianModalOpen.value = false;
                toast.success('New guardian registered successfully.');
            },
            onError: () => {
                toast.error('Failed to register guardian. Please check the form.');
            },
        });
    }
};

// =========================================================================
// DELETE CONFIRMATION
// =========================================================================
const isDeleteModalOpen = ref(false);
const guardianToDelete = ref<Guardian | null>(null);
const deleteForm = useForm({});

const openDeleteModal = (guardian: Guardian) => {
    guardianToDelete.value = guardian;
    isDeleteModalOpen.value = true;
};

const confirmDeleteGuardian = () => {
    if (!guardianToDelete.value) return;

    deleteForm.delete(destroyGuardian(guardianToDelete.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            guardianToDelete.value = null;
            toast.success('Guardian deleted successfully.');
        },
        onError: (errors) => {
            if (errors.error) {
                toast.error(errors.error);
            } else {
                toast.error('Failed to delete guardian.');
            }
        },
    });
};
</script>

<template>
    <Head title="Guardians Directory" />

    <div class="space-y-6 p-4 sm:p-6 lg:p-8">
        <!-- Header & Action Row -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                    Guardians Directory
                </h1>
                <p class="text-sm text-muted-foreground">
                    Register and manage student parents, sponsors, emergency contacts, and portal access credentials.
                </p>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <Button
                    v-if="canCreateGuardian"
                    class="h-10 w-full sm:w-auto gap-2 shadow-sm"
                    @click="openCreateGuardianModal"
                >
                    <Plus class="h-4 w-4" />
                    <span>Register Guardian</span>
                </Button>
            </div>
        </div>

        <!-- KPI Summary Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Total Guardians
                    </CardTitle>
                    <Users class="h-4 w-4 text-primary" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight">
                        {{ stats.total_guardians }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Registered sponsors & parents
                    </p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Linked Students
                    </CardTitle>
                    <GraduationCap class="h-4 w-4 text-emerald-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                        {{ stats.total_linked_students }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Active ward attachments
                    </p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Portal Accounts
                    </CardTitle>
                    <ShieldCheck class="h-4 w-4 text-blue-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight text-blue-600 dark:text-blue-400">
                        {{ stats.portal_active_guardians }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Parents with online result access
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Filter & Search Controls -->
        <Card class="border-border/60 shadow-xs">
            <CardContent class="p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Search by guardian name, phone, student ward, or occupation..."
                            class="pl-9 h-10 w-full"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <select
                            v-model="portalFilter"
                            class="h-10 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option value="all">All Portal Statuses</option>
                            <option value="has_portal">With Portal Login</option>
                            <option value="no_portal">Without Portal Login</option>
                        </select>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Guardians Catalog Table -->
        <Card class="border-border/60 shadow-xs">
            <CardHeader class="px-6 py-4 border-b border-border/40">
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle class="text-base font-semibold">Guardians & Sponsors</CardTitle>
                        <CardDescription>
                            Showing {{ paginationStart }} - {{ paginationEnd }} of {{ filteredGuardians.length }} records
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/40 border-b border-border/60 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-3.5">Guardian Details</th>
                            <th class="px-6 py-3.5">Relationship</th>
                            <th class="px-6 py-3.5">Contact & Location</th>
                            <th class="px-6 py-3.5">Wards (Students)</th>
                            <th class="px-6 py-3.5">Portal Status</th>
                            <th class="px-6 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        <tr
                            v-for="guardian in paginatedGuardians"
                            :key="guardian.id"
                            class="hover:bg-muted/30 transition-colors"
                        >
                            <td class="px-6 py-4">
                                <div class="font-medium text-foreground">
                                    {{ guardian.name }}
                                </div>
                                <div v-if="guardian.occupation" class="text-xs text-muted-foreground flex items-center gap-1 mt-0.5">
                                    <Briefcase class="h-3 w-3" />
                                    <span>{{ guardian.occupation }}</span>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <Badge variant="outline" class="font-normal text-xs">
                                    {{ guardian.relationship }}
                                </Badge>
                            </td>

                            <td class="px-6 py-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-1.5 text-xs text-foreground font-medium">
                                        <Phone class="h-3.5 w-3.5 text-muted-foreground" />
                                        <span>{{ guardian.phone }}</span>
                                    </div>
                                    <div v-if="guardian.email" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <Mail class="h-3.5 w-3.5" />
                                        <span>{{ guardian.email }}</span>
                                    </div>
                                    <div v-if="guardian.address" class="flex items-center gap-1.5 text-xs text-muted-foreground truncate max-w-xs">
                                        <MapPin class="h-3.5 w-3.5 shrink-0" />
                                        <span class="truncate">{{ guardian.address }}</span>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <div v-if="guardian.students && guardian.students.length > 0" class="space-y-1">
                                    <div
                                        v-for="student in guardian.students"
                                        :key="student.id"
                                        class="inline-flex items-center gap-1.5 mr-1 mb-1 px-2 py-0.5 rounded-md bg-secondary/60 text-secondary-foreground text-xs font-medium"
                                    >
                                        <span>{{ student.user?.name ?? 'Student' }}</span>
                                        <span class="text-muted-foreground">({{ student.admission_number }})</span>
                                        <span v-if="student.current_enrollment?.school_class" class="text-[10px] text-primary font-semibold">
                                            [{{ student.current_enrollment.school_class.name }}<template v-if="student.current_enrollment.section"> - {{ student.current_enrollment.section.name }}</template>]
                                        </span>
                                    </div>
                                </div>
                                <div v-else class="text-xs text-muted-foreground italic">
                                    No students assigned
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <Badge
                                    v-if="guardian.user_id"
                                    variant="secondary"
                                    class="bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-200 dark:border-blue-900/50 gap-1 text-xs"
                                >
                                    <ShieldCheck class="h-3 w-3" />
                                    <span>Portal Active</span>
                                </Badge>
                                <span v-else class="text-xs text-muted-foreground">
                                    Contact Only
                                </span>
                            </td>

                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Button
                                        v-if="canUpdateGuardian"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-muted-foreground hover:text-foreground"
                                        title="Edit Profile"
                                        @click="openEditGuardianModal(guardian)"
                                    >
                                        <Edit2 class="h-4 w-4" />
                                    </Button>

                                    <Button
                                        v-if="canDeleteGuardian"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-destructive hover:bg-destructive/10"
                                        title="Delete Guardian"
                                        @click="openDeleteModal(guardian)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="paginatedGuardians.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <HeartHandshake class="h-8 w-8 text-muted-foreground/60" />
                                    <p class="font-medium">No guardians found matching your query.</p>
                                    <p class="text-xs">Adjust your search filters or click "Register Guardian" to add one.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer (15 items per page) -->
            <div
                v-if="filteredGuardians.length > 0"
                class="flex flex-col sm:flex-row items-center justify-between gap-4 px-6 py-4 border-t border-border/40"
            >
                <div class="text-xs text-muted-foreground">
                    Showing <span class="font-medium text-foreground">{{ paginationStart }}</span> to
                    <span class="font-medium text-foreground">{{ paginationEnd }}</span> of
                    <span class="font-medium text-foreground">{{ filteredGuardians.length }}</span> entries
                </div>

                <div class="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="icon"
                        class="h-8 w-8"
                        :disabled="currentPage === 1"
                        @click="currentPage = Math.max(1, currentPage - 1)"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </Button>

                    <div class="flex items-center gap-1 px-1">
                        <Button
                            v-for="pg in displayedPageNumbers"
                            :key="pg"
                            variant="outline"
                            size="sm"
                            class="h-8 min-w-[2rem] px-2 text-xs"
                            :class="{
                                'bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground':
                                    currentPage === pg,
                            }"
                            @click="currentPage = pg"
                        >
                            {{ pg }}
                        </Button>
                    </div>

                    <Button
                        variant="outline"
                        size="icon"
                        class="h-8 w-8"
                        :disabled="currentPage >= totalPages"
                        @click="currentPage = Math.min(totalPages, currentPage + 1)"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </Card>

        <!-- Guardian Form Dialog (Create / Edit) -->
        <Dialog v-model:open="isGuardianModalOpen">
            <DialogScrollContent class="max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {{ editingGuardian ? 'Edit Guardian Profile' : 'Register New Guardian' }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            editingGuardian
                                ? 'Update bio-data, contact details, or credentials for this guardian.'
                                : 'Enter details to register a new student parent, sponsor, or legal guardian.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitGuardianForm" class="space-y-4 py-2">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1 sm:col-span-2">
                            <Label for="name">Full Name <span class="text-destructive">*</span></Label>
                            <Input
                                id="name"
                                v-model="guardianForm.name"
                                placeholder="e.g. Alhaji Ibrahim Danbatta"
                                required
                            />
                            <InputError :message="guardianForm.errors.name" />
                        </div>

                        <div class="space-y-1">
                            <Label for="relationship">Relationship <span class="text-destructive">*</span></Label>
                            <select
                                id="relationship"
                                v-model="guardianForm.relationship"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                                required
                            >
                                <option v-for="rel in relationshipOptions" :key="rel" :value="rel">
                                    {{ rel }}
                                </option>
                            </select>
                            <InputError :message="guardianForm.errors.relationship" />
                        </div>

                        <div class="space-y-1">
                            <Label for="occupation">Occupation</Label>
                            <Input
                                id="occupation"
                                v-model="guardianForm.occupation"
                                placeholder="e.g. Civil Servant, Merchant, Doctor"
                            />
                            <InputError :message="guardianForm.errors.occupation" />
                        </div>

                        <div class="space-y-1">
                            <Label for="phone">Phone Number <span class="text-destructive">*</span></Label>
                            <Input
                                id="phone"
                                v-model="guardianForm.phone"
                                placeholder="e.g. 08031234567"
                                required
                            />
                            <InputError :message="guardianForm.errors.phone" />
                        </div>

                        <div class="space-y-1">
                            <Label for="email">Email Address</Label>
                            <Input
                                id="email"
                                v-model="guardianForm.email"
                                type="email"
                                placeholder="guardian@example.com"
                            />
                            <InputError :message="guardianForm.errors.email" />
                        </div>

                        <div class="space-y-1 sm:col-span-2">
                            <Label for="address">Residential Address</Label>
                            <Input
                                id="address"
                                v-model="guardianForm.address"
                                placeholder="e.g. No. 14 Airport Road, G.R.A, Gusau"
                            />
                            <InputError :message="guardianForm.errors.address" />
                        </div>
                    </div>

                    <!-- Portal Account Toggle & Configuration -->
                    <div class="pt-3 border-t border-border/40 space-y-3">
                        <!-- Case 1: Registering new guardian OR editing guardian without user account yet -->
                        <div v-if="!editingGuardian || !editingGuardian.user_id" class="flex items-center space-x-2">
                            <Checkbox
                                id="create_user_account"
                                :checked="guardianForm.create_user_account"
                                @update:checked="(val: boolean) => (guardianForm.create_user_account = val)"
                            />
                            <Label
                                for="create_user_account"
                                class="text-sm font-medium leading-none cursor-pointer select-none"
                                @click.prevent="guardianForm.create_user_account = !guardianForm.create_user_account"
                            >
                                {{ editingGuardian ? 'Enable Online Portal Login for this Guardian' : 'Enable Online Portal Login for Guardian' }}
                            </Label>
                        </div>

                        <!-- Case 2: Status Banner for guardian who already has a portal user account -->
                        <div
                            v-else
                            class="rounded-lg bg-emerald-500/10 p-3 border border-emerald-500/20 flex items-center justify-between"
                        >
                            <div class="flex items-center gap-2.5">
                                <ShieldCheck class="h-5 w-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">
                                        Portal Account Active
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Username: <span class="font-mono font-medium text-foreground">@{{ editingGuardian.user?.username }}</span>
                                    </p>
                                </div>
                            </div>
                            <Badge variant="outline" class="border-emerald-500/30 text-emerald-600 dark:text-emerald-400">
                                Active
                            </Badge>
                        </div>

                        <!-- Credentials section: shown when create_user_account is true OR when editing an existing account (for password reset) -->
                        <div
                            v-if="guardianForm.create_user_account || (editingGuardian && editingGuardian.user_id)"
                            class="grid grid-cols-1 sm:grid-cols-2 gap-4 rounded-lg bg-muted/40 p-3 border border-border/60"
                        >
                            <!-- Username field: shown when creating user account (new registration OR enabling for first time on edit) -->
                            <div v-if="!editingGuardian || !editingGuardian.user_id" class="space-y-1">
                                <Label for="username">Username (Optional)</Label>
                                <Input
                                    id="username"
                                    v-model="guardianForm.username"
                                    placeholder="Leave blank to auto-generate"
                                />
                                <InputError :message="guardianForm.errors.username" />
                            </div>

                            <!-- Password field -->
                            <div
                                class="space-y-1"
                                :class="{ 'sm:col-span-2': editingGuardian && editingGuardian.user_id }"
                            >
                                <Label for="password">
                                    {{ editingGuardian && editingGuardian.user_id ? 'Reset Password (Optional)' : 'Initial Password' }}
                                </Label>
                                <Input
                                    id="password"
                                    v-model="guardianForm.password"
                                    type="password"
                                    :placeholder="editingGuardian && editingGuardian.user_id ? 'Leave blank to retain current' : 'Min 8 characters (default: password)'"
                                />
                                <InputError :message="guardianForm.errors.password" />
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isGuardianModalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="guardianForm.processing"
                        >
                            <Spinner v-if="guardianForm.processing" class="mr-2 h-4 w-4" />
                            <span>{{ editingGuardian ? 'Save Changes' : 'Register Guardian' }}</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="isDeleteModalOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-destructive">
                        <AlertTriangle class="h-5 w-5" />
                        <span>Delete Guardian</span>
                    </DialogTitle>
                    <DialogDescription>
                        Are you sure you want to remove
                        <span class="font-semibold text-foreground">{{ guardianToDelete?.name }}</span>?
                    </DialogDescription>
                </DialogHeader>

                <div v-if="guardianToDelete && (guardianToDelete.students_count ?? 0) > 0" class="rounded-md bg-destructive/10 p-3 text-xs text-destructive flex items-start gap-2">
                    <AlertTriangle class="h-4 w-4 shrink-0 mt-0.5" />
                    <div>
                        <strong>Deletion Restricted:</strong> This guardian has
                        {{ guardianToDelete.students_count }} student ward(s) currently linked. You must reassign or remove the student ward linkage before deleting this profile.
                    </div>
                </div>

                <div v-else class="text-xs text-muted-foreground">
                    This action will remove the guardian record and their portal user account. This cannot be undone.
                </div>

                <DialogFooter class="pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isDeleteModalOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="deleteForm.processing || (guardianToDelete?.students_count ?? 0) > 0"
                        @click="confirmDeleteGuardian"
                    >
                        <Spinner v-if="deleteForm.processing" class="mr-2 h-4 w-4" />
                        <span>Confirm Delete</span>
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
