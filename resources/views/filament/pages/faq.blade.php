<x-filament-panels::page>
    <div x-data="faqPage(@js($this->getSections()))" class="flex gap-6">
        {{-- Sidebar Navigation (sticky, hidden on screens < lg) --}}
        <nav class="hidden lg:block w-64 shrink-0">
            <div class="sticky top-4 space-y-1 max-h-[calc(100vh-6rem)] overflow-y-auto rounded-xl bg-white dark:bg-gray-800 shadow-md p-4">
                <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Seções</h3>
                <template x-for="section in sections" :key="'nav-' + section.slug">
                    <a
                        :href="'#section-' + section.slug"
                        class="flex items-center gap-2 px-3 py-2 text-[13px] rounded-lg transition-colors duration-150"
                        :class="activeSection === section.slug
                            ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300 font-medium'
                            : 'text-gray-600 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700'"
                        @click.prevent="scrollToSection(section.slug)"
                        x-text="section.title"
                    ></a>
                </template>
            </div>
        </nav>

        {{-- Main Content --}}
        <div class="flex-1 min-w-0 space-y-6">
            {{-- Search Field --}}
            <div class="rounded-xl bg-white dark:bg-gray-800 shadow-md p-4">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <input
                        type="text"
                        x-model="searchQuery"
                        @input.debounce.300ms="filterSections()"
                        placeholder="Pesquisar perguntas..."
                        class="block w-full pl-10 pr-4 py-3 text-base border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                    />
                </div>
            </div>

            {{-- FAQ Sections (Accordion) --}}
            <template x-for="section in filteredSections" :key="section.slug">
                <div :id="'section-' + section.slug" class="rounded-xl bg-white dark:bg-gray-800 shadow-md overflow-hidden">
                    {{-- Section Header --}}
                    <button
                        @click="toggleSection(section.slug)"
                        class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                    >
                        <span class="flex items-center gap-3">
                            <span class="text-lg font-semibold text-gray-900 dark:text-white" x-text="section.title"></span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded-full" x-text="section.questions.length + ' perguntas'"></span>
                        </span>
                        <svg
                            class="w-5 h-5 text-gray-500 transition-transform duration-200"
                            :class="expandedSections[section.slug] ? 'rotate-180' : ''"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                        >
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                        </svg>
                    </button>

                    {{-- Section Questions --}}
                    <div x-show="expandedSections[section.slug]" x-collapse>
                        <div class="border-t border-gray-200 dark:border-gray-700">
                            <template x-for="(item, qIndex) in section.questions" :key="section.slug + '-q-' + qIndex">
                                <div
                                    class="border-b last:border-b-0"
                                    :class="item.destructive
                                        ? 'border-amber-200 dark:border-amber-800/50 bg-amber-50/50 dark:bg-amber-900/10'
                                        : 'border-gray-100 dark:border-gray-700/50'"
                                >
                                    {{-- Question Header --}}
                                    <button
                                        @click="toggleQuestion(section.slug, qIndex)"
                                        class="w-full flex items-center justify-between px-6 py-3 text-left transition-colors"
                                        :class="item.destructive
                                            ? 'hover:bg-amber-100/50 dark:hover:bg-amber-900/20'
                                            : 'hover:bg-gray-50 dark:hover:bg-gray-700/30'"
                                    >
                                        <span class="flex items-center gap-2 text-base font-medium" :class="item.destructive ? 'text-amber-800 dark:text-amber-200' : 'text-gray-800 dark:text-gray-100'">
                                            <span x-show="item.destructive" class="text-amber-500 text-lg">⚠️</span>
                                            <span x-text="item.question"></span>
                                        </span>
                                        <svg
                                            class="w-4 h-4 text-gray-400 shrink-0 ml-2 transition-transform duration-200"
                                            :class="expandedQuestions[section.slug + '-' + qIndex] ? 'rotate-180' : ''"
                                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                                        >
                                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                        </svg>
                                    </button>

                                    {{-- Answer --}}
                                    <div x-show="expandedQuestions[section.slug + '-' + qIndex]" x-collapse>
                                        <div
                                            class="px-6 pb-5 text-base text-gray-700 dark:text-white prose prose-base dark:prose-invert max-w-none leading-relaxed [&_li]:dark:text-white [&_p]:dark:text-white [&_strong]:dark:text-white"
                                            x-html="item.answer"
                                        ></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Empty State --}}
            <div x-show="filteredSections.length === 0 && searchQuery.length >= 2" class="rounded-xl bg-white dark:bg-gray-800 shadow-md p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
                <p class="mt-4 text-base text-gray-500 dark:text-gray-400">Nenhuma pergunta encontrada para o termo pesquisado</p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('faqPage', (sections) => ({
                sections: sections,
                filteredSections: sections,
                searchQuery: '',
                expandedSections: {},
                expandedQuestions: {},
                activeSection: null,

                init() {
                    this.handleDeepLink();
                    this.initScrollSpy();
                },

                toggleSection(slug) {
                    this.expandedSections[slug] = !this.expandedSections[slug];
                },

                toggleQuestion(sectionSlug, qIndex) {
                    const key = sectionSlug + '-' + qIndex;
                    this.expandedQuestions[key] = !this.expandedQuestions[key];
                },

                scrollToSection(slug) {
                    this.expandedSections[slug] = true;
                    this.activeSection = slug;
                    this.$nextTick(() => {
                        const el = document.getElementById('section-' + slug);
                        if (el) {
                            el.scrollIntoView({ behavior: 'smooth' });
                        }
                    });
                },

                // Normaliza texto removendo acentos e convertendo para minúsculas
                normalizeText(text) {
                    return text.normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .toLowerCase();
                },

                // Filtra seções e perguntas com base no searchQuery
                filterSections() {
                    if (this.searchQuery.length < 2) {
                        this.filteredSections = this.sections;
                        return;
                    }
                    const query = this.normalizeText(this.searchQuery);
                    this.filteredSections = this.sections
                        .map(section => ({
                            ...section,
                            questions: section.questions.filter(q =>
                                this.normalizeText(q.question).includes(query) ||
                                this.normalizeText(q.answer).includes(query)
                            )
                        }))
                        .filter(section => section.questions.length > 0);
                },

                // Lê parâmetro ?secao= da URL e navega até a seção
                handleDeepLink() {
                    const params = new URLSearchParams(window.location.search);
                    const secao = params.get('secao');
                    if (secao) {
                        const exists = this.sections.find(s => s.slug === secao);
                        if (exists) {
                            this.expandedSections[secao] = true;
                            this.activeSection = secao;
                            this.$nextTick(() => {
                                document.getElementById('section-' + secao)
                                    ?.scrollIntoView({ behavior: 'smooth' });
                            });
                        }
                    }
                },

                // Observa scroll para destacar seção ativa no sidebar
                initScrollSpy() {
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                const id = entry.target.id;
                                if (id && id.startsWith('section-')) {
                                    this.activeSection = id.replace('section-', '');
                                }
                            }
                        });
                    }, { threshold: 0.3 });

                    this.$nextTick(() => {
                        this.sections.forEach(section => {
                            const el = document.getElementById('section-' + section.slug);
                            if (el) observer.observe(el);
                        });
                    });
                },
            }));
        });
    </script>
</x-filament-panels::page>
