<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    laravel: String,
    php: String,
});
</script>

<template>
    <Head title="Foundation" />

    <CContainer class="py-5">
        <CRow class="justify-content-center">
            <CCol :md="8">
                <CCard>
                    <CCardHeader>
                        <strong>Shaniena v3 — Foundation</strong>
                    </CCardHeader>
                    <CCardBody>
                        <p class="text-body-secondary">
                            Laravel {{ laravel }} + Inertia + Vue 3 + CoreUI (Bootstrap 5).
                        </p>
                        <CListGroup flush>
                            <CListGroupItem>Laravel <CBadge color="primary">{{ laravel }}</CBadge></CListGroupItem>
                            <CListGroupItem>PHP <CBadge color="primary">{{ php }}</CBadge></CListGroupItem>
                        </CListGroup>
                        <CAlert color="primary" class="mt-3 mb-0">
                            Brand primary token is live: this alert uses <code>#e53637</code>.
                        </CAlert>
                    </CCardBody>
                </CCard>
            </CCol>
        </CRow>
    </CContainer>
</template>
