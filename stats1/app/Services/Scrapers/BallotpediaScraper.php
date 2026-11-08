<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class BallotpediaScraper extends BaseScraper
{
    protected int $requestsPerMinute = 5;

    public function getIdentifier(): string
    {
        return 'ballotpedia';
    }

    public function getBaseUrl(): string
    {
        return 'https://ballotpedia.org';
    }

    /**
     * Scrape Ballotpedia for candidate and election data
     */
    public function scrape(): array
    {
        $results = [
            'presidential' => $this->scrapePresidentialCandidates(),
            'senate' => $this->scrapeSenateRaces(),
            'governors' => $this->scrapeGovernorRaces(),
            'elections' => $this->scrapeUpcomingElections(),
            'scraped_at' => now()->toIso8601String(),
        ];

        Log::info("[Ballotpedia] Scraped candidate and election data");
        
        return $results;
    }

    /**
     * Scrape presidential candidates
     */
    public function scrapePresidentialCandidates(): array
    {
        $url = $this->getBaseUrl() . '/Presidential_candidates,_2024';
        $html = $this->fetch($url);

        if (!$html) {
            return [];
        }

        return $this->extractCandidates($html, 'presidential');
    }

    /**
     * Scrape senate races
     */
    public function scrapeSenateRaces(): array
    {
        $url = $this->getBaseUrl() . '/United_States_Senate_elections,_2024';
        $html = $this->fetch($url);

        if (!$html) {
            return [];
        }

        return $this->extractRaces($html, 'senate');
    }

    /**
     * Scrape governor races
     */
    public function scrapeGovernorRaces(): array
    {
        $url = $this->getBaseUrl() . '/Gubernatorial_elections,_2024';
        $html = $this->fetch($url);

        if (!$html) {
            return [];
        }

        return $this->extractRaces($html, 'governor');
    }

    /**
     * Scrape upcoming elections
     */
    public function scrapeUpcomingElections(): array
    {
        $url = $this->getBaseUrl() . '/Elections_calendar';
        $html = $this->fetch($url);

        if (!$html) {
            return [];
        }

        return $this->extractElectionDates($html);
    }

    /**
     * Scrape a specific candidate page
     */
    public function scrapeCandidate(string $candidateName): ?array
    {
        $slug = str_replace(' ', '_', $candidateName);
        $url = $this->getBaseUrl() . '/' . $slug;
        $html = $this->fetch($url);

        if (!$html) {
            return null;
        }

        return $this->extractCandidateDetails($html, $candidateName);
    }

    /**
     * Extract candidates from a list page
     */
    protected function extractCandidates(string $html, string $office): array
    {
        $crawler = $this->getCrawler($html);
        $candidates = [];

        // Ballotpedia structure varies, but often uses tables or lists
        // Targeting generic tables that look like candidate lists
        $crawler->filter('table.wikitable tr')->each(function (Crawler $row) use (&$candidates, $office) {
            $cells = $row->filter('td');
            if ($cells->count() >= 2) {
                try {
                    $nameCell = $cells->eq(0);
                    $name = $this->cleanText($nameCell->text());
                    
                    if (strlen($name) > 2 && !$this->isHeaderRow($name)) {
                        $party = $this->detectParty($row->html());
                        $link = $nameCell->filter('a')->attr('href');
                        
                        $candidates[] = [
                            'name' => $name,
                            'party' => $party,
                            'office' => $office,
                            'url' => str_starts_with($link, 'http') ? $link : $this->getBaseUrl() . $link,
                            'status' => $this->detectStatus($row->html()),
                        ];
                    }
                } catch (\Exception $e) {
                    // Skip
                }
            }
        });

        return array_slice($candidates, 0, 50);
    }

    /**
     * Extract races data
     */
    protected function extractRaces(string $html, string $type): array
    {
        $crawler = $this->getCrawler($html);
        $races = [];

        $crawler->filter('table.wikitable tr')->each(function (Crawler $row) use (&$races, $type) {
            $cells = $row->filter('td');
            if ($cells->count() >= 1) {
                try {
                    $stateCell = $cells->eq(0);
                    $state = $this->cleanText($stateCell->text());
                    
                    if (strlen($state) > 2 && !$this->isHeaderRow($state)) {
                        $incumbent = '';
                        $incumbentParty = '';
                        
                        // Try to find incumbent info in other cells
                        if ($cells->count() > 1) {
                             $incumbent = $this->cleanText($cells->eq(1)->text());
                        }

                        $races[] = [
                            'state' => $state,
                            'type' => $type,
                            'incumbent' => $incumbent,
                            'incumbent_party' => $this->detectParty($row->html()),
                            'rating' => $this->detectRaceRating($row->text()),
                        ];
                    }
                } catch (\Exception $e) {
                    // Skip
                }
            }
        });

        return $races;
    }

    /**
     * Extract upcoming election dates
     */
    protected function extractElectionDates(string $html): array
    {
        $crawler = $this->getCrawler($html);
        $elections = [];

        // Ballotpedia election calendar tables usually have: Date | State | Office | Type
        $crawler->filter('table.wikitable tr')->each(function (Crawler $row) use (&$elections) {
            $cells = $row->filter('td');
            if ($cells->count() >= 3) {
                try {
                    // Date is usually the first column (or sometimes a header above, but let's assume table for now)
                    // Actually, looking at typical Ballotpedia calendars: State | Date | Office | Type
                    // Or: Date | State | Election
                    
                    // Based on HTML analysis:
                    // Col 0: State (with link)
                    // Col 1: Office/Election Name
                    // Col 2: Details
                    // Col 3: Date
                    
                    if ($cells->count() >= 4) {
                        $state = $this->cleanText($cells->eq(0)->text());
                        $title = $this->cleanText($cells->eq(1)->text());
                        $details = $this->cleanText($cells->eq(2)->text());
                        $dateText = $this->cleanText($cells->eq(3)->text());
                        
                        // Verify date
                        if ($this->isDate($dateText)) {
                            // Extract URL from State or Office column
                            $url = '';
                            $linkNode = $cells->eq(1)->filter('a')->first(); // Try office link first
                            if ($linkNode->count() === 0) {
                                $linkNode = $cells->eq(0)->filter('a')->first(); // Fallback to state link
                            }
                            
                            if ($linkNode->count() > 0) {
                                $href = $linkNode->attr('href');
                                $url = str_starts_with($href, 'http') ? $href : $this->getBaseUrl() . $href;
                            }

                            $elections[] = [
                                'date' => $dateText,
                                'type' => 'Election',
                                'state' => $state,
                                'title' => $title,
                                'url' => $url,
                                'details' => $details,
                            ];
                        }
                    }

                } catch (\Exception $e) {
                    // Skip
                }
            }
        });

        return array_slice($elections, 0, 50);
    }

    /**
     * Check if string looks like a date
     */
    protected function isDate(string $text): bool
    {
        return (bool) strtotime($text);
    }

    /**
     * Extract detailed candidate info from candidate page
     */
    protected function extractCandidateDetails(string $html, string $name): array
    {
        $crawler = $this->getCrawler($html);
        $details = [
            'name' => $name,
            'party' => $this->detectParty($html),
        ];

        try {
            // Infobox extraction
            $infobox = $crawler->filter('table.infobox')->first();
            if ($infobox->count() > 0) {
                $infobox->filter('tr')->each(function (Crawler $row) use (&$details) {
                    $th = $row->filter('th')->first();
                    $td = $row->filter('td')->first();
                    
                    if ($th->count() > 0 && $td->count() > 0) {
                        $label = strtolower(trim($th->text()));
                        $value = $this->cleanText($td->text());

                        if (str_contains($label, 'office')) $details['office'] = $value;
                        if (str_contains($label, 'born')) $details['born'] = $value;
                        if (str_contains($label, 'education')) $details['education'] = $value;
                        
                        $link = $td->filter('a.external')->attr('href');
                        if ($link && (str_contains($label, 'website') || str_contains($label, 'campaign'))) {
                            $details['website'] = $link;
                        }
                    }
                });
            }

            // Bio extraction (first substantial paragraph)
            $crawler->filter('p')->each(function (Crawler $p) use (&$details) {
                if (!isset($details['bio'])) {
                    $text = $this->cleanText($p->text());
                    if (strlen($text) > 100) {
                        $details['bio'] = substr($text, 0, 500);
                    }
                }
            });

        } catch (\Exception $e) {
            // Partial data is fine
        }

        return $details;
    }

    /**
     * Detect party affiliation from HTML/Text
     */
    protected function detectParty(string $text): string
    {
        $text = strtolower($text);
        
        if (str_contains($text, 'democratic') || str_contains($text, '(d)')) {
            return 'D';
        }
        if (str_contains($text, 'republican') || str_contains($text, '(r)')) {
            return 'R';
        }
        if (str_contains($text, 'independent') || str_contains($text, '(i)')) {
            return 'I';
        }
        if (str_contains($text, 'libertarian') || str_contains($text, '(l)')) {
            return 'L';
        }
        if (str_contains($text, 'green') || str_contains($text, '(g)')) {
            return 'G';
        }

        return '';
    }

    /**
     * Detect candidate status
     */
    protected function detectStatus(string $text): string
    {
        $text = strtolower($text);
        
        if (str_contains($text, 'withdrew') || str_contains($text, 'dropped out')) {
            return 'withdrawn';
        }
        if (str_contains($text, 'suspended')) {
            return 'suspended';
        }
        if (str_contains($text, 'nominee') || str_contains($text, 'presumptive')) {
            return 'nominee';
        }
        if (str_contains($text, 'declared') || str_contains($text, 'announced')) {
            return 'declared';
        }

        return 'active';
    }

    /**
     * Detect race rating
     */
    protected function detectRaceRating(string $text): string
    {
        $text = strtolower($text);
        
        if (str_contains($text, 'safe dem') || str_contains($text, 'solid d')) {
            return 'Safe D';
        }
        if (str_contains($text, 'safe rep') || str_contains($text, 'solid r')) {
            return 'Safe R';
        }
        if (str_contains($text, 'likely dem') || str_contains($text, 'likely d')) {
            return 'Likely D';
        }
        if (str_contains($text, 'likely rep') || str_contains($text, 'likely r')) {
            return 'Likely R';
        }
        if (str_contains($text, 'lean dem') || str_contains($text, 'lean d')) {
            return 'Lean D';
        }
        if (str_contains($text, 'lean rep') || str_contains($text, 'lean r')) {
            return 'Lean R';
        }
        if (str_contains($text, 'toss-up') || str_contains($text, 'tossup')) {
            return 'Toss-up';
        }

        return '';
    }

    /**
     * Check if this is a header row
     */
    protected function isHeaderRow(string $text): bool
    {
        $headers = ['name', 'party', 'state', 'status', 'candidate', 'office', 'date'];
        return in_array(strtolower(trim($text)), $headers);
    }
}
