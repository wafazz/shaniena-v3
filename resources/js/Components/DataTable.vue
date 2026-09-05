<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import EmptyState from './EmptyState.vue';

/**
 * Server-paged table over CoreUI's free CTable + CPagination.
 *
 * Not CSmartTable: that is a CoreUI PRO component, and at 20k+ orders a
 * client-side table would load every row into the browser — which is exactly
 * what makes the source's jQuery DataTables screens slow.
 */
const props = defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    meta: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    emptyTitle: { type: String, default: 'Nothing here' },
    emptyBody: { type: String, default: '' },
    rowKey: { type: String, default: 'id' },
    // Full-page tables hold a readable minimum and scroll; a table inside a
    // narrow card has to fit the card instead.
    minWidth: { type: String, default: '60rem' },
});

const pages = computed(() => {
    if (!props.meta || props.meta.last_page <= 1) {
        return [];
    }

    const { current_page: current, last_page: last } = props.meta;
    const span = 2;
    const out = [];

    for (let i = Math.max(1, current - span); i <= Math.min(last, current + span); i++) {
        out.push(i);
    }

    return out;
});

function goTo(page) {
    router.get(window.location.pathname, { ...currentQuery(), page }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function currentQuery() {
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}
</script>

<template>
    <div>
        <div class="table-responsive">
            <CTable hover class="align-middle mb-0 data-table" :style="{ minWidth }">
                <CTableHead>
                    <CTableRow>
                        <CTableHeaderCell
                            v-for="column in columns"
                            :key="column.key"
                            :class="[column.align === 'right' ? 'num' : '', column.headerClass]"
                            scope="col"
                        >
                            <slot :name="`header:${column.key}`">{{ column.label }}</slot>
                        </CTableHeaderCell>
                    </CTableRow>
                </CTableHead>

                <CTableBody>
                    <!-- Loading is a real state: the source had none anywhere,
                         so a slow page just looked empty. -->
                    <CTableRow v-if="loading" v-for="n in 5" :key="`skeleton-${n}`">
                        <CTableDataCell v-for="column in columns" :key="column.key">
                            <span class="skeleton" :style="{ width: `${40 + ((n * 13) % 45)}%` }"></span>
                        </CTableDataCell>
                    </CTableRow>

                    <CTableRow v-else v-for="row in rows" :key="row[rowKey]">
                        <CTableDataCell
                            v-for="column in columns"
                            :key="column.key"
                            :class="[column.align === 'right' ? 'num' : '', column.cellClass]"
                        >
                            <slot :name="`cell:${column.key}`" :row="row">{{ row[column.key] }}</slot>
                        </CTableDataCell>
                    </CTableRow>
                </CTableBody>
            </CTable>
        </div>

        <EmptyState v-if="!loading && !rows.length" :title="emptyTitle" :body="emptyBody">
            <slot name="empty" />
        </EmptyState>

        <div v-if="meta && rows.length" class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-3">
            <p class="small text-body-secondary mb-0">
                Showing <span class="num">{{ meta.from }}</span>–<span class="num">{{ meta.to }}</span>
                of <span class="num">{{ meta.total.toLocaleString() }}</span>
            </p>

            <CPagination v-if="pages.length" size="sm" class="mb-0" aria-label="Pagination">
                <CPaginationItem :disabled="meta.current_page === 1" role="button" @click="goTo(1)">First</CPaginationItem>
                <CPaginationItem :disabled="meta.current_page === 1" role="button" @click="goTo(meta.current_page - 1)">Prev</CPaginationItem>
                <CPaginationItem
                    v-for="page in pages"
                    :key="page"
                    role="button"
                    :active="page === meta.current_page"
                    @click="goTo(page)"
                >{{ page }}</CPaginationItem>
                <CPaginationItem :disabled="meta.current_page === meta.last_page" role="button" @click="goTo(meta.current_page + 1)">Next</CPaginationItem>
                <CPaginationItem :disabled="meta.current_page === meta.last_page" role="button" @click="goTo(meta.last_page)">Last</CPaginationItem>
            </CPagination>
        </div>
    </div>
</template>
