<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Clock,
    Globe,
    Mail,
    MapPin,
    Phone,
    Send,
    Sparkles,
} from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import FrontendLayout from '@/layouts/FrontendLayout.vue';

interface ContactInfo {
    school_name?: string;
    address?: string | null;
    phone?: string | null;
    email?: string | null;
    office_hours?: string | null;
    map_embed_url?: string | null;
    social_links?: Record<string, string> | null;
}

interface Props {
    contactInfo: ContactInfo;
}

const props = defineProps<Props>();

const form = useForm({
    name: '',
    email: '',
    phone: '',
    subject: '',
    message: '',
});

const submitSuccess = ref(false);

const submitInquiry = () => {
    form.post('/contact', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            submitSuccess.value = true;
            setTimeout(() => {
                submitSuccess.value = false;
            }, 6000);
        },
    });
};
</script>

<template>
    <FrontendLayout>
        <Head title="Contact Us & Campus Visits" />

        <!-- 1. Header Banner -->
        <div class="bg-slate-900 text-white py-12 lg:py-16 border-b border-slate-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl space-y-3">
                    <div class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-amber-400 bg-amber-400/10 px-3 py-1 rounded-full border border-amber-400/20">
                        <Mail class="size-3.5" />
                        <span>Communications Desk</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white">
                        Get In Touch With Us
                    </h1>
                    <p class="text-sm sm:text-base text-slate-300">
                        Have questions regarding student admissions, subjects, or visiting our campus? Send a message and our administration will respond promptly.
                    </p>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">
                <!-- 2. Left: Contact Information & Visiting Hours -->
                <div class="lg:col-span-5 space-y-6">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-600">Institutional Details</span>
                        <h2 class="text-2xl font-extrabold text-slate-900 mt-1">Campus Administration</h2>
                        <p class="text-xs sm:text-sm text-slate-500 mt-1">
                            Visit or call during official working hours for prompt attention.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-start gap-3 bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
                            <div class="size-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0 mt-0.5">
                                <MapPin class="size-5" />
                            </div>
                            <div>
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Campus Address</span>
                                <p class="text-xs sm:text-sm font-semibold text-slate-800 mt-0.5">
                                    {{ contactInfo.address || 'Zaria Road, Gusau, Zamfara State, Nigeria' }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
                            <div class="size-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 mt-0.5">
                                <Phone class="size-5" />
                            </div>
                            <div>
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Telephone Hotline</span>
                                <p class="text-xs sm:text-sm font-semibold text-slate-800 mt-0.5">
                                    {{ contactInfo.phone || '+234 803 123 4567' }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
                            <div class="size-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 mt-0.5">
                                <Mail class="size-5" />
                            </div>
                            <div>
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Official Email</span>
                                <p class="text-xs sm:text-sm font-semibold text-slate-800 mt-0.5">
                                    {{ contactInfo.email || 'admissions@school.edu.ng' }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
                            <div class="size-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center shrink-0 mt-0.5">
                                <Clock class="size-5" />
                            </div>
                            <div>
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Visiting & Office Hours</span>
                                <p class="text-xs sm:text-sm font-semibold text-slate-800 mt-0.5">
                                    {{ contactInfo.office_hours || 'Mon - Fri: 8:00 AM - 3:30 PM' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Map / Location Embed -->
                    <div v-if="contactInfo.map_embed_url" class="rounded-2xl overflow-hidden border border-slate-200 shadow-xs bg-slate-100 aspect-16/9">
                        <iframe
                            :src="contactInfo.map_embed_url"
                            width="100%"
                            height="100%"
                            style="border:0;"
                            allowfullscreen="false"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            title="School Location Map"
                        />
                    </div>
                </div>

                <!-- 3. Right: Contact Inquiry Form -->
                <div class="lg:col-span-7 bg-white p-8 rounded-3xl border border-slate-200 shadow-sm space-y-6">
                    <div class="space-y-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-600">Send Inquiry</span>
                        <h2 class="text-2xl font-extrabold text-slate-900">How Can We Help You?</h2>
                        <p class="text-xs sm:text-sm text-slate-500">
                            Fill in your inquiry details below. Messages are saved securely and addressed by our administrative desk.
                        </p>
                    </div>

                    <div
                        v-if="submitSuccess"
                        class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center gap-3 text-xs sm:text-sm font-medium animate-in fade-in"
                    >
                        <CheckCircle2 class="size-5 text-emerald-600 shrink-0" />
                        <span>Your inquiry has been submitted successfully! Thank you for contacting us.</span>
                    </div>

                    <form @submit.prevent="submitInquiry" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="name" class="text-xs font-bold text-slate-700">Full Name *</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    placeholder="e.g. Ibrahim Bello"
                                    required
                                    class="text-xs"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="space-y-1.5">
                                <Label for="email" class="text-xs font-bold text-slate-700">Email Address *</Label>
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    placeholder="e.g. ibrahim@example.com"
                                    required
                                    class="text-xs"
                                />
                                <InputError :message="form.errors.email" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="phone" class="text-xs font-bold text-slate-700">Phone Number (Optional)</Label>
                                <Input
                                    id="phone"
                                    v-model="form.phone"
                                    type="text"
                                    placeholder="e.g. +234 803 000 0000"
                                    class="text-xs"
                                />
                                <InputError :message="form.errors.phone" />
                            </div>

                            <div class="space-y-1.5">
                                <Label for="subject" class="text-xs font-bold text-slate-700">Inquiry Subject *</Label>
                                <Input
                                    id="subject"
                                    v-model="form.subject"
                                    type="text"
                                    placeholder="e.g. Admission for JSS 1"
                                    required
                                    class="text-xs"
                                />
                                <InputError :message="form.errors.subject" />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="message" class="text-xs font-bold text-slate-700">Message / Question *</Label>
                            <textarea
                                id="message"
                                v-model="form.message"
                                rows="5"
                                placeholder="Type your inquiry, question, or visit request in detail..."
                                required
                                class="w-full bg-white text-slate-900 placeholder-slate-400 text-xs sm:text-sm p-3 rounded-xl border border-slate-200 focus:outline-hidden focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition-colors"
                            />
                            <InputError :message="form.errors.message" />
                        </div>

                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full sm:w-auto bg-slate-900 hover:bg-slate-800 text-white font-bold px-6 py-2.5 rounded-xl text-xs flex items-center justify-center gap-2"
                        >
                            <Spinner v-if="form.processing" class="size-4" />
                            <Send v-else class="size-3.5 text-amber-400" />
                            <span>Submit Message</span>
                        </Button>
                    </form>
                </div>
            </div>
        </div>
    </FrontendLayout>
</template>
