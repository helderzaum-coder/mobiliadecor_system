<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Faker\Factory as Faker;

class FaqPageTest extends TestCase
{
    /**
     * Normalize text by removing accents and converting to lowercase.
     * PHP equivalent of the Alpine.js normalizeText() function.
     */
    private function normalizeText(string $text): string
    {
        $normalized = \Normalizer::normalize($text, \Normalizer::FORM_D);
        $normalized = preg_replace('/[\x{0300}-\x{036f}]/u', '', $normalized);
        return mb_strtolower($normalized);
    }

    /**
     * Filter sections based on search query.
     * PHP equivalent of the Alpine.js filterSections() function.
     */
    private function filterSections(array $sections, string $query): array
    {
        if (mb_strlen($query) < 2) {
            return $sections;
        }

        $normalizedQuery = $this->normalizeText($query);
        $filtered = [];

        foreach ($sections as $section) {
            $matchingQuestions = array_filter($section['questions'], function ($q) use ($normalizedQuery) {
                return str_contains($this->normalizeText($q['question']), $normalizedQuery) ||
                       str_contains($this->normalizeText($q['answer']), $normalizedQuery);
            });

            if (!empty($matchingQuestions)) {
                $section['questions'] = array_values($matchingQuestions);
                $filtered[] = $section;
            }
        }

        return $filtered;
    }

    /**
     * Get FAQ data from the Faq page class.
     */
    private function getFaqSections(): array
    {
        $faq = new \App\Filament\Pages\Faq();
        return $faq->getSections();
    }

    /**
     * Extract all plain text substrings (2+ chars) from questions and answers.
     * Only extracts purely alphabetic words to avoid issues with punctuation.
     */
    private function extractSubstrings(array $sections): array
    {
        $substrings = [];

        foreach ($sections as $section) {
            foreach ($section['questions'] as $q) {
                // Extract alphabetic words from question text
                preg_match_all('/[\p{L}]{2,}/u', $q['question'], $matches);
                foreach ($matches[0] as $word) {
                    // Skip words longer than 20 chars (likely concatenated from HTML stripping)
                    if (mb_strlen($word) > 20) {
                        continue;
                    }
                    $substrings[] = [
                        'text' => $word,
                        'section_slug' => $section['slug'],
                    ];
                }

                // Extract alphabetic words from answer text (strip HTML, add spaces between tags)
                $plainAnswer = preg_replace('/<[^>]+>/', ' ', $q['answer']);
                $plainAnswer = html_entity_decode($plainAnswer, ENT_QUOTES, 'UTF-8');
                preg_match_all('/[\p{L}]{2,}/u', $plainAnswer, $matches);
                foreach ($matches[0] as $word) {
                    // Skip words longer than 20 chars (likely concatenated from HTML stripping)
                    if (mb_strlen($word) > 20) {
                        continue;
                    }
                    $substrings[] = [
                        'text' => $word,
                        'section_slug' => $section['slug'],
                    ];
                }
            }
        }

        return $substrings;
    }

    /**
     * Generate a case variation of a string.
     */
    private function generateCaseVariation(string $text, int $seed): string
    {
        $variation = $seed % 4;

        switch ($variation) {
            case 0:
                return mb_strtoupper($text);
            case 1:
                return mb_strtolower($text);
            case 2:
                // Mixed case: alternate upper/lower
                $result = '';
                $len = mb_strlen($text);
                for ($i = 0; $i < $len; $i++) {
                    $char = mb_substr($text, $i, 1);
                    $result .= ($i % 2 === 0) ? mb_strtoupper($char) : mb_strtolower($char);
                }
                return $result;
            case 3:
                // Random case per character
                $result = '';
                $len = mb_strlen($text);
                for ($i = 0; $i < $len; $i++) {
                    $char = mb_substr($text, $i, 1);
                    $result .= (($seed + $i) % 3 === 0) ? mb_strtoupper($char) : mb_strtolower($char);
                }
                return $result;
            default:
                return $text;
        }
    }

    /**
     * Add accents to a string for testing accent insensitivity.
     */
    private function addAccents(string $text): string
    {
        $accentMap = [
            'a' => ['á', 'à', 'â', 'ã', 'ä'],
            'e' => ['é', 'è', 'ê', 'ë'],
            'i' => ['í', 'ì', 'î', 'ï'],
            'o' => ['ó', 'ò', 'ô', 'õ', 'ö'],
            'u' => ['ú', 'ù', 'û', 'ü'],
            'c' => ['ç'],
            'n' => ['ñ'],
            'A' => ['Á', 'À', 'Â', 'Ã', 'Ä'],
            'E' => ['É', 'È', 'Ê', 'Ë'],
            'I' => ['Í', 'Ì', 'Î', 'Ï'],
            'O' => ['Ó', 'Ò', 'Ô', 'Õ', 'Ö'],
            'U' => ['Ú', 'Ù', 'Û', 'Ü'],
            'C' => ['Ç'],
            'N' => ['Ñ'],
        ];

        $result = '';
        $len = mb_strlen($text);

        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($text, $i, 1);
            if (isset($accentMap[$char])) {
                $accents = $accentMap[$char];
                $result .= $accents[array_rand($accents)];
            } else {
                $result .= $char;
            }
        }

        return $result;
    }

    // =========================================================================
    // Property Test 6.2: Section content completeness
    // =========================================================================

    /**
     * Feature: faq-system, Property 1: Section content completeness
     * For any FAQ section in the data array, it SHALL contain: a non-empty slug,
     * a non-empty title, and at least 2 question-answer pairs where each question
     * and answer are non-empty strings.
     *
     * **Validates: Requirements 1.4, 6.5, 6.6**
     *
     * @test
     */
    public function property_section_content_completeness(): void
    {
        $sections = $this->getFaqSections();

        $this->assertNotEmpty($sections, 'FAQ sections array should not be empty');

        foreach ($sections as $index => $section) {
            // Slug must be a non-empty string
            $this->assertArrayHasKey('slug', $section, "Section at index {$index} is missing 'slug'");
            $this->assertIsString($section['slug'], "Section at index {$index} slug must be a string");
            $this->assertNotEmpty($section['slug'], "Section at index {$index} slug must not be empty");

            // Title must be a non-empty string
            $this->assertArrayHasKey('title', $section, "Section at index {$index} is missing 'title'");
            $this->assertIsString($section['title'], "Section at index {$index} title must be a string");
            $this->assertNotEmpty($section['title'], "Section at index {$index} title must not be empty");

            // Questions array must exist with at least 2 items
            $this->assertArrayHasKey('questions', $section, "Section '{$section['slug']}' is missing 'questions'");
            $this->assertIsArray($section['questions'], "Section '{$section['slug']}' questions must be an array");
            $this->assertGreaterThanOrEqual(
                2,
                count($section['questions']),
                "Section '{$section['slug']}' must have at least 2 question-answer pairs, found " . count($section['questions'])
            );

            // Each question-answer pair must have non-empty question and answer strings
            foreach ($section['questions'] as $qIndex => $qa) {
                $this->assertArrayHasKey('question', $qa, "Section '{$section['slug']}' Q#{$qIndex} is missing 'question'");
                $this->assertIsString($qa['question'], "Section '{$section['slug']}' Q#{$qIndex} question must be a string");
                $this->assertNotEmpty($qa['question'], "Section '{$section['slug']}' Q#{$qIndex} question must not be empty");

                $this->assertArrayHasKey('answer', $qa, "Section '{$section['slug']}' Q#{$qIndex} is missing 'answer'");
                $this->assertIsString($qa['answer'], "Section '{$section['slug']}' Q#{$qIndex} answer must be a string");
                $this->assertNotEmpty($qa['answer'], "Section '{$section['slug']}' Q#{$qIndex} answer must not be empty");
            }
        }
    }

    // =========================================================================
    // Property Test 6.3: Destructive action warnings
    // =========================================================================

    /**
     * Feature: faq-system, Property 2: Destructive action warnings
     * For any FAQ question marked as `destructive: true`, the answer text SHALL
     * contain a warning indicator (the word "⚠️" or "aviso" or "irreversível"
     * or "não pode ser desfeita").
     *
     * **Validates: Requirements 2.5**
     *
     * @test
     */
    public function property_destructive_action_warnings(): void
    {
        $sections = $this->getFaqSections();
        $destructiveQuestions = [];

        foreach ($sections as $section) {
            foreach ($section['questions'] as $qa) {
                if (isset($qa['destructive']) && $qa['destructive'] === true) {
                    $destructiveQuestions[] = [
                        'section' => $section['slug'],
                        'question' => $qa['question'],
                        'answer' => $qa['answer'],
                    ];
                }
            }
        }

        $this->assertNotEmpty(
            $destructiveQuestions,
            'There should be at least one destructive question in the FAQ data to validate this property'
        );

        foreach ($destructiveQuestions as $dq) {
            $answer = $dq['answer'];
            $containsWarning =
                str_contains($answer, '⚠️') ||
                str_contains(mb_strtolower($answer), 'aviso') ||
                str_contains(mb_strtolower($answer), 'irreversível') ||
                str_contains(mb_strtolower($answer), 'não pode ser desfeita');

            $this->assertTrue(
                $containsWarning,
                "Destructive question in section '{$dq['section']}' ('{$dq['question']}') must contain a warning indicator (⚠️, 'aviso', 'irreversível', or 'não pode ser desfeita') in its answer"
            );
        }
    }

    // =========================================================================
    // Property Test 6.4: Search filter case and accent insensitivity
    // =========================================================================

    /**
     * Feature: faq-system, Property 3: Search filter case and accent insensitivity
     * For any FAQ item whose question or answer contains a substring S,
     * searching for any case variation or accent-stripped variation of S (with length >= 2)
     * SHALL include that item in the filtered results.
     *
     * **Validates: Requirements 3.2**
     *
     * @test
     */
    public function property_search_filter_is_case_and_accent_insensitive(): void
    {
        $faker = Faker::create('pt_BR');
        $sections = $this->getFaqSections();
        $substrings = $this->extractSubstrings($sections);

        $this->assertNotEmpty($substrings, 'FAQ data should contain extractable substrings');

        $iterations = 120;
        $passCount = 0;

        for ($i = 0; $i < $iterations; $i++) {
            // Pick a random substring from the FAQ data
            $entry = $substrings[array_rand($substrings)];
            $originalText = $entry['text'];
            $sectionSlug = $entry['section_slug'];

            // Skip if substring is less than 2 characters after normalization
            if (mb_strlen($this->normalizeText($originalText)) < 2) {
                $iterations++; // Compensate to ensure 120 valid iterations
                if ($iterations > 500) {
                    break; // Safety valve
                }
                continue;
            }

            // Generate a variation: case change, accent addition, or both
            $variationType = $i % 3;
            switch ($variationType) {
                case 0:
                    // Case variation only
                    $searchQuery = $this->generateCaseVariation($originalText, $i);
                    break;
                case 1:
                    // Accent variation (add accents to base text)
                    $searchQuery = $this->addAccents($originalText);
                    break;
                case 2:
                    // Both: case variation + accent
                    $withAccents = $this->addAccents($originalText);
                    $searchQuery = $this->generateCaseVariation($withAccents, $i);
                    break;
                default:
                    $searchQuery = $originalText;
            }

            // Filter sections with the variation
            $filtered = $this->filterSections($sections, $searchQuery);

            // The section containing the original text should appear in results
            $filteredSlugs = array_column($filtered, 'slug');

            $this->assertContains(
                $sectionSlug,
                $filteredSlugs,
                sprintf(
                    'Iteration %d: Searching for "%s" (variation of "%s") should return section "%s". Got sections: [%s]',
                    $i,
                    $searchQuery,
                    $originalText,
                    $sectionSlug,
                    implode(', ', $filteredSlugs)
                )
            );

            $passCount++;
        }

        $this->assertGreaterThanOrEqual(100, $passCount, 'Should have at least 100 valid test iterations');
    }

    // =========================================================================
    // Property Test 6.5: Short query returns all items
    // =========================================================================

    /**
     * Feature: faq-system, Property 4: Short query returns all items
     * For any string of length 0 or 1 used as search query,
     * the filter function SHALL return all FAQ sections with all their questions unchanged.
     *
     * **Validates: Requirements 3.3**
     *
     * @test
     */
    public function property_short_query_returns_all_items(): void
    {
        $faker = Faker::create('pt_BR');
        $sections = $this->getFaqSections();

        // Test with empty string
        $filtered = $this->filterSections($sections, '');
        $this->assertEquals(
            $sections,
            $filtered,
            'Empty string query should return all sections unchanged'
        );

        // Test with various single characters (100+ iterations)
        $singleChars = array_merge(
            range('a', 'z'),
            range('A', 'Z'),
            range('0', '9'),
            ['!', '@', '#', '$', '%', '&', '*', '(', ')', '-', '+', '=', ' '],
            ['á', 'é', 'í', 'ó', 'ú', 'ã', 'õ', 'ç', 'ñ', 'ü', 'ê', 'â', 'ô'],
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ã', 'Õ', 'Ç', 'Ñ', 'Ü', 'Ê', 'Â', 'Ô']
        );

        $iterations = 0;
        foreach ($singleChars as $char) {
            $filtered = $this->filterSections($sections, $char);
            $this->assertEquals(
                $sections,
                $filtered,
                sprintf(
                    'Single character query "%s" (length %d) should return all sections unchanged',
                    $char,
                    mb_strlen($char)
                )
            );
            $iterations++;
        }

        // Add random single characters from Faker to reach 100+
        while ($iterations < 110) {
            $randomChar = $faker->randomElement([
                $faker->randomLetter(),
                $faker->randomDigitNotNull(),
                mb_substr($faker->word(), 0, 1),
            ]);
            // Ensure it's a single character
            $randomChar = mb_substr((string) $randomChar, 0, 1);

            $filtered = $this->filterSections($sections, $randomChar);
            $this->assertEquals(
                $sections,
                $filtered,
                sprintf(
                    'Single character query "%s" should return all sections unchanged',
                    $randomChar
                )
            );
            $iterations++;
        }

        $this->assertGreaterThanOrEqual(100, $iterations, 'Should have at least 100 test iterations');
    }

    // =========================================================================
    // Property Test 6.6: Sections without matches are hidden
    // =========================================================================

    /**
     * Feature: faq-system, Property 5: Sections without matches are hidden
     * For any search query of length >= 2 that matches at least one question but not all sections,
     * the filtered results SHALL contain only sections that have at least one matching question,
     * and no section with zero matching questions SHALL appear.
     *
     * **Validates: Requirements 3.6**
     *
     * @test
     */
    public function property_sections_without_matches_are_hidden(): void
    {
        $faker = Faker::create('pt_BR');
        $sections = $this->getFaqSections();

        $this->assertGreaterThan(1, count($sections), 'FAQ should have more than 1 section for this test');

        $iterations = 0;
        $maxAttempts = 300; // Safety valve for finding partial matches
        $attempts = 0;

        while ($iterations < 100 && $attempts < $maxAttempts) {
            $attempts++;

            // Strategy: pick a word from a specific section that is unlikely to appear in all sections
            $randomSectionIndex = array_rand($sections);
            $randomSection = $sections[$randomSectionIndex];
            $randomQuestion = $randomSection['questions'][array_rand($randomSection['questions'])];

            // Get alphabetic words from the question or answer text
            $sourceText = $faker->randomElement([$randomQuestion['question'], strip_tags($randomQuestion['answer'])]);
            preg_match_all('/[\p{L}]{2,}/u', $sourceText, $wordMatches);
            $words = $wordMatches[0];

            if (empty($words)) {
                continue;
            }

            $word = $words[array_rand($words)];

            // Filter with this word
            $filtered = $this->filterSections($sections, $word);

            // Skip if the query matches ALL sections (not a partial match)
            if (count($filtered) === count($sections)) {
                continue;
            }

            // Skip if no results (empty state, different property)
            if (empty($filtered)) {
                continue;
            }

            // Now verify the property: every section in filtered results has at least one matching question
            $normalizedQuery = $this->normalizeText($word);

            foreach ($filtered as $filteredSection) {
                $hasMatch = false;
                foreach ($filteredSection['questions'] as $q) {
                    if (
                        str_contains($this->normalizeText($q['question']), $normalizedQuery) ||
                        str_contains($this->normalizeText($q['answer']), $normalizedQuery)
                    ) {
                        $hasMatch = true;
                        break;
                    }
                }
                $this->assertTrue(
                    $hasMatch,
                    sprintf(
                        'Section "%s" in filtered results should have at least one question matching "%s"',
                        $filteredSection['slug'],
                        $word
                    )
                );
            }

            // Verify that sections NOT in filtered results have zero matching questions
            $filteredSlugs = array_column($filtered, 'slug');
            foreach ($sections as $section) {
                if (in_array($section['slug'], $filteredSlugs)) {
                    continue; // Already verified above
                }

                // This section should have NO matching questions
                foreach ($section['questions'] as $q) {
                    $matchesQuestion = str_contains($this->normalizeText($q['question']), $normalizedQuery);
                    $matchesAnswer = str_contains($this->normalizeText($q['answer']), $normalizedQuery);

                    $this->assertFalse(
                        $matchesQuestion || $matchesAnswer,
                        sprintf(
                            'Section "%s" should NOT be excluded if it has a matching question. Query: "%s", Question: "%s"',
                            $section['slug'],
                            $word,
                            mb_substr($q['question'], 0, 50)
                        )
                    );
                }
            }

            $iterations++;
        }

        $this->assertGreaterThanOrEqual(100, $iterations,
            sprintf('Should have at least 100 valid partial-match iterations (got %d in %d attempts)', $iterations, $attempts)
        );
    }
}
