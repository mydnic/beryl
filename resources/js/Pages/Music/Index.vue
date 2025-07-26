<template>
    <div class="max-w-7xl mx-auto">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                        Beryl
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">
                        Manage and fix metadata for your music collection
                    </p>
                </div>
                <UButton
                    to="/scan"
                    method="post"
                    icon="i-lucide-scan-line"
                    size="lg"
                    color="primary"
                    variant="solid"
                >
                    Scan Library
                </UButton>
            </div>

            <!-- Search and Filter Controls -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex flex-col lg:flex-row gap-4">
                    <!-- Search Bar -->
                    <div class="flex-1">
                        <UInput
                            v-model="searchQuery"
                            icon="i-lucide-search"
                            placeholder="Search by title, artist, album, or file path..."
                            size="lg"
                            @input="debouncedSearch"
                        />
                    </div>

                    <!-- Sort Controls -->
                    <div class="flex gap-3 items-center">
                        <USelect
                            v-model="sortBy"
                            :items="sortOptions"
                            placeholder="Sort by"
                            size="lg"
                            class="w-40"
                            @change="updateSort"
                        />
                        <UButton
                            :icon="sortOrder === 'asc' ? 'i-lucide-arrow-up' : 'i-lucide-arrow-down'"
                            :color="sortOrder === 'asc' ? 'green' : 'blue'"
                            variant="outline"
                            size="lg"
                            @click="toggleSortOrder"
                        >
                            {{ sortOrder === 'asc' ? 'A-Z' : 'Z-A' }}
                        </UButton>

                        <!-- Needs Fixing Toggle -->
                        <div class="flex items-center gap-2">
                            <USwitch
                                v-model="needsFixing"
                                @change="updateNeedsFixing"
                            />
                            <label
                                class="text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer flex items-center"
                                @click="needsFixing = !needsFixing"
                            >
                                <UIcon
                                    name="i-lucide-wrench"
                                    class="w-4 h-4 mr-1"
                                />
                                Needs fixing only
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Active Filters Display -->
                <div
                    v-if="searchQuery || sortBy !== 'created_at' || needsFixing"
                    class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700"
                >
                    <span class="text-sm text-gray-600 dark:text-gray-400">Active filters:</span>
                    <UBadge
                        v-if="searchQuery"
                        color="blue"
                        variant="soft"
                        class="gap-1"
                    >
                        <UIcon
                            name="i-lucide-search"
                            class="w-3 h-3"
                        />
                        "{{ searchQuery }}"
                        <UButton
                            icon="i-lucide-x"
                            size="2xs"
                            color="blue"
                            variant="ghost"
                            @click="clearSearch"
                        />
                    </UBadge>
                    <UBadge
                        v-if="sortBy !== 'created_at'"
                        color="green"
                        variant="soft"
                        class="gap-1"
                    >
                        <UIcon
                            name="i-lucide-arrow-up-down"
                            class="w-3 h-3"
                        />
                        {{ getSortLabel(sortBy) }} ({{ sortOrder }})
                        <UButton
                            icon="i-lucide-x"
                            size="2xs"
                            color="green"
                            variant="ghost"
                            @click="resetSort"
                        />
                    </UBadge>
                    <UBadge
                        v-if="needsFixing"
                        color="orange"
                        variant="soft"
                        class="gap-1"
                    >
                        <UIcon
                            name="i-lucide-wrench"
                            class="w-3 h-3"
                        />
                        Needs fixing only
                        <UButton
                            icon="i-lucide-x"
                            size="2xs"
                            color="orange"
                            variant="ghost"
                            @click="clearNeedsFixing"
                        />
                    </UBadge>
                </div>
            </div>
        </div>

        <!-- Job Progress Tracker -->
        <JobProgressTracker :initial-stats="job_stats" />

        <!-- Results Summary -->
        <div
            v-if="allMusics.length"
            class="mb-6"
        >
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Showing {{ allMusics.length }} of {{ pagination.total }} tracks
                    <span v-if="searchQuery">matching "{{ searchQuery }}"</span>
                    <span
                        v-if="needsFixing"
                        class="text-orange-600 dark:text-orange-400 font-medium"
                    >
                        that need metadata fixing
                    </span>
                </p>
                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <UIcon
                        name="i-lucide-music"
                        class="w-4 h-4"
                    />
                    {{ pagination.total }} total tracks
                </div>
            </div>
        </div>

        <!-- Music Grid -->
        <div
            v-if="allMusics.length"
            class="space-y-3 mb-8"
        >
            <MusicCard
                v-for="music in allMusics"
                :key="music.id"
                :music="music"
                class="transition-all duration-200 hover:shadow-md"
            />
        </div>

        <!-- Empty State -->
        <div
            v-else
            class="text-center py-16"
        >
            <div class="max-w-md mx-auto">
                <UIcon
                    name="i-lucide-music-off"
                    class="w-16 h-16 text-gray-400 mx-auto mb-4"
                />
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                    {{ getEmptyStateTitle() }}
                </h3>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    {{ getEmptyStateMessage() }}
                </p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <UButton
                        v-if="searchQuery || needsFixing"
                        variant="outline"
                        @click="clearAllFilters"
                    >
                        Clear All Filters
                    </UButton>
                    <UButton
                        to="/scan"
                        method="post"
                        icon="i-lucide-scan-line"
                        color="primary"
                    >
                        {{ searchQuery || needsFixing ? 'Scan for More Music' : 'Scan Music Library' }}
                    </UButton>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div
            v-if="allMusics.length && pagination.total > pagination.per_page"
            class="flex justify-center py-8"
        >
            <UPagination
                :items-per-page="pagination.per_page"
                :total="pagination.total"
                :page="pagination.current_page"
                @update:page="handlePageChange"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import JobProgressTracker from '@/Components/JobProgressTracker.vue'

// Simple debounce function
const debounce = (func, wait) => {
    let timeout
    return function executedFunction (...args) {
        const later = () => {
            clearTimeout(timeout)
            func(...args)
        }
        clearTimeout(timeout)
        timeout = setTimeout(later, wait)
    }
}

const props = defineProps({
    musics: {
        type: Object,
        required: true
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            sort_by: 'created_at',
            sort_order: 'desc',
            needs_fixing: false
        })
    },
    job_stats: {
        type: Object,
        required: true
    }
})

// Reactive data
const searchQuery = ref(props.filters.search)
const sortBy = ref(props.filters.sort_by)
const sortOrder = ref(props.filters.sort_order)
const needsFixing = ref(props.filters.needs_fixing)

// Computed properties
const allMusics = computed(() => props.musics?.data || [])

const pagination = computed(() => ({
    per_page: props.musics.per_page,
    total: props.musics.total,
    current_page: props.musics.current_page
}))

const sortOptions = [
    { label: 'Artist Name', value: 'artist' },
    { label: 'Date Added', value: 'created_at' },
    { label: 'Release Year', value: 'release_year' }
]

// Methods
const getSortLabel = (value) => {
    const option = sortOptions.find(opt => opt.value === value)
    return option ? option.label : value
}

const buildQueryParams = () => {
    const params = new URLSearchParams()

    if (searchQuery.value) {
        params.set('search', searchQuery.value)
    }

    if (sortBy.value !== 'created_at') {
        params.set('sort_by', sortBy.value)
    }

    if (sortOrder.value !== 'desc') {
        params.set('sort_order', sortOrder.value)
    }

    if (needsFixing.value) {
        params.set('needs_fixing', 'true')
    }

    return params.toString()
}

const updateUrl = () => {
    const queryString = buildQueryParams()
    const url = queryString ? `/?${queryString}` : '/'

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    })
}

const debouncedSearch = debounce(() => {
    updateUrl()
}, 500)

const updateSort = () => {
    updateUrl()
}

const toggleSortOrder = () => {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
    updateUrl()
}

const clearSearch = () => {
    searchQuery.value = ''
    updateUrl()
}

const resetSort = () => {
    sortBy.value = 'created_at'
    sortOrder.value = 'desc'
    updateUrl()
}

const clearNeedsFixing = () => {
    needsFixing.value = false
    updateUrl()
}

const clearAllFilters = () => {
    searchQuery.value = ''
    sortBy.value = 'created_at'
    sortOrder.value = 'desc'
    needsFixing.value = false
    updateUrl()
}

const getEmptyStateTitle = () => {
    if (needsFixing.value) {
        return 'No tracks need fixing'
    }
    if (searchQuery.value) {
        return 'No tracks found'
    }
    return 'No music in your library'
}

const getEmptyStateMessage = () => {
    if (needsFixing.value && searchQuery.value) {
        return `No tracks match your search for "${searchQuery.value}" that need metadata fixing. Try different keywords or clear the filter.`
    }
    if (needsFixing.value) {
        return 'All your tracks have correct metadata! You can scan for more music or clear the filter to see all tracks.'
    }
    if (searchQuery.value) {
        return `No tracks match your search for "${searchQuery.value}". Try different keywords.`
    }
    return 'Start by scanning your music directory to populate your library.'
}

const updateNeedsFixing = () => {
    updateUrl()
}

const handlePageChange = (page) => {
    const queryString = buildQueryParams()
    const baseUrl = queryString ? `/?${queryString}` : '/'
    const url = `${baseUrl}${queryString ? '&' : '?'}page=${page}`

    router.visit(url, {
        preserveState: true,
        preserveScroll: false
    })
}

// Watch for prop changes to sync reactive data
watch(() => props.filters, (newFilters) => {
    searchQuery.value = newFilters.search
    sortBy.value = newFilters.sort_by
    sortOrder.value = newFilters.sort_order
    needsFixing.value = newFilters.needs_fixing
}, { immediate: true })
</script>
