import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login, register } from '@/routes';
import type { SharedData } from '@/types';

// Workflow Feature Item
function WorkflowItem({
    icon,
    title,
    description
}: {
    icon: React.ReactNode;
    title: string;
    description: string;
}) {
    return (
        <div className="p-6 text-center">
            <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                {icon}
            </div>
            <h3 className="mb-2 font-semibold text-foreground">{title}</h3>
            <p className="text-sm text-muted-foreground">{description}</p>
        </div>
    );
}

// Dashboard Preview Card with Charts
function DashboardPreview() {
    return (
        <div className="relative mx-auto max-w-4xl">
            <div className="grid grid-cols-3 gap-4">
                {/* Analytics Card */}
                <div className="col-span-1 rounded-2xl border border-border bg-card p-4 shadow-lg">
                    <div className="mb-2 text-xs text-muted-foreground">Political Finance</div>
                    <div className="space-y-2">
                        <div className="flex items-center gap-2">
                            <div className="h-2 w-2 rounded-full bg-chart-2"></div>
                            <span className="text-xs text-foreground">FEC Data</span>
                            <span className="ml-auto text-xs font-semibold text-foreground">85%</span>
                        </div>
                        <div className="h-1.5 rounded-full bg-muted overflow-hidden">
                            <div className="h-full w-[85%] bg-chart-2 rounded-full"></div>
                        </div>
                        <div className="flex items-center gap-2">
                            <div className="h-2 w-2 rounded-full bg-chart-3"></div>
                            <span className="text-xs text-foreground">Economic</span>
                            <span className="ml-auto text-xs font-semibold text-foreground">72%</span>
                        </div>
                        <div className="h-1.5 rounded-full bg-muted overflow-hidden">
                            <div className="h-full w-[72%] bg-chart-3 rounded-full"></div>
                        </div>
                    </div>
                </div>

                {/* Chart Card */}
                <div className="col-span-1 rounded-2xl border border-border bg-card p-4 shadow-lg">
                    <div className="mb-2 text-xs text-muted-foreground">Correlation Analysis</div>
                    <div className="flex items-end gap-1 h-20">
                        {[40, 65, 45, 80, 55, 70, 90, 60, 75, 85].map((h, i) => (
                            <div
                                key={i}
                                className="flex-1 rounded-sm bg-gradient-to-t from-chart-4/40 to-chart-4"
                                style={{ height: `${h}%` }}
                            />
                        ))}
                    </div>
                </div>

                {/* Stats Card */}
                <div className="col-span-1 rounded-2xl border border-border bg-card p-4 shadow-lg">
                    <div className="mb-2 text-xs text-muted-foreground">Data Pipeline</div>
                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <span className="text-xs text-foreground">Records</span>
                            <span className="text-sm font-semibold text-primary">2.4M</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-xs text-foreground">Sources</span>
                            <span className="text-sm font-semibold text-chart-2">4</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-xs text-foreground">Status</span>
                            <span className="inline-flex items-center gap-1 text-xs text-chart-2">
                                <span className="h-1.5 w-1.5 rounded-full bg-chart-2 animate-pulse"></span>
                                Normalized
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Feature Badges */}
            <div className="mt-4 flex justify-center gap-4">
                <div className="flex items-center gap-2 rounded-full border border-border bg-card px-4 py-2 text-xs shadow-md">
                    <svg className="h-4 w-4 text-chart-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                    <span className="text-foreground">Unified Ingestion</span>
                </div>
                <div className="flex items-center gap-2 rounded-full border border-border bg-card px-4 py-2 text-xs shadow-md">
                    <svg className="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span className="text-foreground">Correlation-Ready</span>
                </div>
            </div>
        </div>
    );
}

// Feature Section with Image
function FeatureSection({
    title,
    description,
    imagePosition = 'right',
    children
}: {
    title: string;
    description: string;
    imagePosition?: 'left' | 'right';
    children: React.ReactNode;
}) {
    return (
        <div className={`grid lg:grid-cols-2 gap-12 items-center ${imagePosition === 'left' ? 'lg:flex-row-reverse' : ''}`}>
            <div className={imagePosition === 'left' ? 'lg:order-2' : ''}>
                <h2 className="text-3xl lg:text-4xl font-serif font-bold text-foreground mb-4">{title}</h2>
                <p className="text-muted-foreground mb-6">{description}</p>
            </div>
            <div className={imagePosition === 'left' ? 'lg:order-1' : ''}>
                {children}
            </div>
        </div>
    );
}

// Data Source Card Component
function DataSourceCard({
    title,
    subtitle,
    progress,
    color
}: {
    title: string;
    subtitle: string;
    progress: number;
    color: string;
}) {
    return (
        <div className="rounded-xl border border-border bg-card p-4 shadow-md hover:shadow-lg transition-shadow">
            <div className="flex items-start gap-3">
                <div className={`h-10 w-10 rounded-lg ${color} flex items-center justify-center text-white text-sm font-bold`}>
                    {title.charAt(0)}
                </div>
                <div className="flex-1">
                    <h4 className="font-semibold text-foreground text-sm">{title}</h4>
                    <p className="text-xs text-muted-foreground">{subtitle}</p>
                </div>
            </div>
            <div className="mt-3">
                <div className="flex justify-between text-xs mb-1">
                    <span className="text-muted-foreground">Normalized</span>
                    <span className="font-medium text-foreground">{progress}%</span>
                </div>
                <div className="h-1.5 rounded-full bg-muted overflow-hidden">
                    <div className={`h-full rounded-full ${color}`} style={{ width: `${progress}%` }}></div>
                </div>
            </div>
        </div>
    );
}

// Icons
const SignalIcon = () => (
    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
    </svg>
);

const DatabaseIcon = () => (
    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
    </svg>
);

const SyncIcon = () => (
    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
    </svg>
);

const CorrelateIcon = () => (
    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
    </svg>
);

const VerifyIcon = () => (
    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
    </svg>
);

const TraceIcon = () => (
    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
    </svg>
);

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="CivicSignal - Connect the Data That Shapes Power">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,600,700&family=inter:400,500,600,700" rel="stylesheet" />
                <meta name="description" content="CivicSignal unifies political, economic, and public-opinion data so you can discover what's connected—and what actually drives outcomes." />
            </Head>

            <div className="min-h-screen bg-background" style={{ fontFamily: "'Inter', sans-serif" }}>
                {/* Navigation */}
                <header className="sticky top-0 z-50 w-full border-b border-border/50 bg-background/95 backdrop-blur-sm">
                    <div className="container mx-auto flex h-16 items-center justify-between px-4 lg:px-8">
                        <div className="flex items-center gap-2">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                <SignalIcon />
                            </div>
                            <span className="text-lg font-bold text-foreground">CivicSignal</span>
                        </div>
                        <nav className="hidden md:flex items-center gap-8 text-sm">
                            <a href="#features" className="text-muted-foreground hover:text-foreground transition-colors">Features</a>
                            <a href="#solutions" className="text-muted-foreground hover:text-foreground transition-colors">Solutions</a>
                            <a href="#about" className="text-muted-foreground hover:text-foreground transition-colors">About</a>
                        </nav>
                        <div className="flex items-center gap-4">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-flex items-center justify-center rounded-full bg-primary px-5 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                                    >
                                        Log in
                                    </Link>
                                    {canRegister && (
                                        <Link
                                            href={register()}
                                            className="inline-flex items-center justify-center rounded-full bg-primary px-5 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                                        >
                                            Get Started
                                        </Link>
                                    )}
                                </>
                            )}
                        </div>
                    </div>
                </header>

                {/* Hero Section */}
                <section className="py-16 lg:py-24">
                    <div className="container mx-auto px-4 lg:px-8 text-center">
                        <p className="text-sm font-medium text-primary mb-4">Civic Analytics Infrastructure</p>

                        <h1 className="text-4xl lg:text-6xl font-bold text-foreground mb-6" style={{ fontFamily: "'Playfair Display', serif" }}>
                            Connect the data that
                            <br />
                            <span className="text-primary">shapes power.</span>
                        </h1>

                        <p className="text-lg text-muted-foreground mb-8 max-w-2xl mx-auto">
                            CivicSignal unifies political, economic, and public-opinion data so you
                            can discover what's connected—and what actually drives outcomes.
                        </p>

                        <div className="flex flex-col sm:flex-row gap-4 justify-center mb-12">
                            <Link
                                href={canRegister ? register() : login()}
                                className="inline-flex items-center justify-center rounded-full bg-primary px-8 py-3 text-sm font-medium text-primary-foreground transition-all hover:bg-primary/90 hover:shadow-lg"
                            >
                                Start Connecting Data
                            </Link>
                            <a
                                href="#features"
                                className="inline-flex items-center justify-center rounded-full border border-border bg-card px-8 py-3 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                            >
                                <svg className="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" />
                                </svg>
                                See How It Works
                            </a>
                        </div>

                        {/* Dashboard Preview */}
                        <DashboardPreview />
                    </div>
                </section>

                {/* Trust Logos / Data Sources */}
                <section className="py-8 border-y border-border bg-muted/30">
                    <div className="container mx-auto px-4 lg:px-8">
                        <p className="text-center text-xs text-muted-foreground mb-6">Pulls from trusted civic data sources</p>
                        <div className="flex flex-wrap justify-center items-center gap-8 lg:gap-16 opacity-60">
                            {['FEC', 'FRED', 'Polling Data', 'Election Results'].map((name) => (
                                <div key={name} className="text-lg font-bold text-muted-foreground">
                                    {name}
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Brand Narrative Section */}
                <section id="about" className="py-20 bg-muted/10">
                    <div className="container mx-auto px-4 lg:px-8 max-w-4xl text-center">
                        <h2 className="text-3xl lg:text-4xl font-bold text-foreground mb-8" style={{ fontFamily: "'Playfair Display', serif" }}>
                            We don't need more data.
                            <br />
                            We need more <span className="text-primary">connection</span>.
                        </h2>
                        <div className="text-lg text-muted-foreground space-y-6">
                            <p>
                                Because money doesn't move without motives. Outcomes don't happen without conditions.
                                And public opinion doesn't exist without context.
                            </p>
                            <p>
                                When we measure the pieces separately, we tell stories.
                                <br />
                                <strong className="text-foreground">When we unify them, we uncover relationships.</strong>
                            </p>
                        </div>
                    </div>
                </section>

                {/* Three Pillars Section */}
                <section id="features" className="py-20">
                    <div className="container mx-auto px-4 lg:px-8">
                        <div className="text-center mb-16">
                            <h2 className="text-3xl lg:text-4xl font-bold text-foreground mb-4" style={{ fontFamily: "'Playfair Display', serif" }}>
                                From siloed stats to
                                <br />
                                civic truth
                            </h2>
                            <p className="text-muted-foreground max-w-xl mx-auto">
                                The underlying engine that makes rigorous analysis possible.
                            </p>
                        </div>

                        <div className="grid md:grid-cols-3 gap-8">
                            <div className="text-center p-8 rounded-2xl border border-border bg-card hover:shadow-lg transition-shadow">
                                <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-primary">
                                    <DatabaseIcon />
                                </div>
                                <h3 className="text-xl font-semibold text-foreground mb-3">Unified Ingestion</h3>
                                <p className="text-muted-foreground">
                                    Automatically collects heterogeneous civic datasets from multiple siloed sources.
                                </p>
                            </div>
                            <div className="text-center p-8 rounded-2xl border border-border bg-card hover:shadow-lg transition-shadow">
                                <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-chart-2/10 text-chart-2">
                                    <SyncIcon />
                                </div>
                                <h3 className="text-xl font-semibold text-foreground mb-3">Normalization Layer</h3>
                                <p className="text-muted-foreground">
                                    Makes mismatched sources comparable across time—reducing error-prone manual merging.
                                </p>
                            </div>
                            <div className="text-center p-8 rounded-2xl border border-border bg-card hover:shadow-lg transition-shadow">
                                <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-chart-3/10 text-chart-3">
                                    <CorrelateIcon />
                                </div>
                                <h3 className="text-xl font-semibold text-foreground mb-3">Correlation-Ready Output</h3>
                                <p className="text-muted-foreground">
                                    Enables analysis like "inflation vs fundraising"—test relationships, don't debate narratives.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Workflows Section */}
                <section className="py-20 bg-muted/20">
                    <div className="container mx-auto px-4 lg:px-8">
                        <div className="text-center mb-16">
                            <h2 className="text-3xl lg:text-4xl font-bold text-foreground mb-4" style={{ fontFamily: "'Playfair Display', serif" }}>
                                Evidence, not assumption
                            </h2>
                            <p className="text-muted-foreground">Rigor made usable. Scholarly-grade, operator-friendly.</p>
                        </div>

                        <div className="grid md:grid-cols-3 lg:grid-cols-6 gap-4">
                            <WorkflowItem
                                icon={<DatabaseIcon />}
                                title="Collect"
                                description="Automated ingestion from multiple API sources"
                            />
                            <WorkflowItem
                                icon={<SyncIcon />}
                                title="Normalize"
                                description="Harmonize time-series with daily frequency"
                            />
                            <WorkflowItem
                                icon={<CorrelateIcon />}
                                title="Correlate"
                                description="Test relationships across domains"
                            />
                            <WorkflowItem
                                icon={<TraceIcon />}
                                title="Trace"
                                description="Traceable sources, explainable methods"
                            />
                            <WorkflowItem
                                icon={<VerifyIcon />}
                                title="Verify"
                                description="Accuracy beats hot takes"
                            />
                            <WorkflowItem
                                icon={<SignalIcon />}
                                title="Signal"
                                description="Find signals inside democracy's noise"
                            />
                        </div>
                    </div>
                </section>

                {/* Data Sources Section */}
                <section id="solutions" className="py-20">
                    <div className="container mx-auto px-4 lg:px-8">
                        <FeatureSection
                            title="One dataset. Real-world answers."
                            description="Stop struggling with siloed data. CivicSignal pulls from political finance, economic indicators, polling, and election results—bringing them into one structure designed for correlation and insight."
                            imagePosition="right"
                        >
                            <div className="space-y-4">
                                <DataSourceCard
                                    title="Political Finance"
                                    subtitle="FEC Campaign Contributions"
                                    progress={92}
                                    color="bg-primary"
                                />
                                <DataSourceCard
                                    title="Economic Indicators"
                                    subtitle="FRED Federal Reserve Data"
                                    progress={88}
                                    color="bg-chart-2"
                                />
                                <DataSourceCard
                                    title="Public Opinion"
                                    subtitle="Polling & Sentiment Data"
                                    progress={85}
                                    color="bg-chart-3"
                                />
                                <DataSourceCard
                                    title="Election Results"
                                    subtitle="Historical Outcomes Data"
                                    progress={95}
                                    color="bg-chart-4"
                                />
                            </div>
                        </FeatureSection>
                    </div>
                </section>

                {/* Analysis Questions Section */}
                <section className="py-20 bg-muted/20">
                    <div className="container mx-auto px-4 lg:px-8">
                        <FeatureSection
                            title="Ask better questions. Answer them with evidence."
                            description="Instead of debating narratives, test relationships. CivicSignal enables questions that were previously impossible to answer rigorously."
                            imagePosition="left"
                        >
                            <div className="rounded-2xl border border-border bg-card p-6 shadow-xl">
                                <div className="space-y-4">
                                    {[
                                        "Does inflation pressure fundraising?",
                                        "Does sentiment lead outcomes?",
                                        "Does funding follow economic shocks?",
                                        "How do macro conditions affect incumbents?"
                                    ].map((question, i) => (
                                        <div key={i} className="flex items-center gap-3 p-3 rounded-lg hover:bg-muted/50 transition-colors">
                                            <div className="flex-shrink-0 h-8 w-8 rounded-full bg-primary/10 flex items-center justify-center text-primary text-sm font-bold">
                                                {i + 1}
                                            </div>
                                            <span className="text-foreground font-medium">{question}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </FeatureSection>
                    </div>
                </section>

                {/* Reports Section */}
                <section className="py-20">
                    <div className="container mx-auto px-4 lg:px-8">
                        <FeatureSection
                            title="Uncover what's actually driving what we're seeing."
                            description="The world doesn't happen in silos. Money, sentiment, outcomes, and the economy move together—now your data systems can too."
                            imagePosition="right"
                        >
                            <div className="rounded-2xl border border-border bg-card p-6 shadow-xl">
                                <div className="mb-4">
                                    <span className="text-sm font-medium text-foreground">Correlation Analysis</span>
                                </div>
                                <div className="flex items-end gap-2 h-32 mb-4">
                                    {[45, 60, 35, 75, 55, 80, 65, 90, 70, 85, 50, 95].map((h, i) => (
                                        <div key={i} className="flex-1 flex flex-col justify-end gap-1">
                                            <div
                                                className="rounded-t bg-primary/80"
                                                style={{ height: `${h * 0.4}%` }}
                                            />
                                            <div
                                                className="rounded-b bg-chart-4/60"
                                                style={{ height: `${h * 0.6}%` }}
                                            />
                                        </div>
                                    ))}
                                </div>
                                <div className="flex justify-between text-xs text-muted-foreground">
                                    <span>Political Finance</span>
                                    <span>Economic Indicators</span>
                                </div>
                            </div>
                        </FeatureSection>
                    </div>
                </section>

                {/* Values Section */}
                <section className="py-20 bg-muted/10">
                    <div className="container mx-auto px-4 lg:px-8">
                        <div className="text-center mb-16">
                            <h2 className="text-3xl lg:text-4xl font-bold text-foreground mb-4" style={{ fontFamily: "'Playfair Display', serif" }}>
                                Our non-negotiables
                            </h2>
                        </div>
                        <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-6 max-w-5xl mx-auto">
                            {[
                                { title: "Integrity over intensity", desc: "Accuracy beats hot takes" },
                                { title: "Transparency", desc: "Traceable sources, explainable methods" },
                                { title: "Service to society", desc: "Insight in pursuit of better decisions" },
                                { title: "Rigor made usable", desc: "Scholarly-grade, operator-friendly" },
                            ].map((value, i) => (
                                <div key={i} className="text-center p-6">
                                    <h3 className="font-semibold text-foreground mb-2">{value.title}</h3>
                                    <p className="text-sm text-muted-foreground">{value.desc}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* CTA */}
                <section className="py-20 bg-primary/5">
                    <div className="container mx-auto px-4 lg:px-8 text-center">
                        <h2 className="text-3xl lg:text-4xl font-bold text-foreground mb-4" style={{ fontFamily: "'Playfair Display', serif" }}>
                            Ready to connect the data
                            <br />
                            that shapes public life?
                        </h2>
                        <p className="text-muted-foreground mb-8 max-w-xl mx-auto">
                            Decisions grounded in evidence, not assumption. Start with CivicSignal today.
                        </p>
                        <div className="flex flex-col sm:flex-row gap-4 justify-center">
                            <Link
                                href={canRegister ? register() : login()}
                                className="inline-flex items-center justify-center rounded-full bg-primary px-8 py-3 text-sm font-medium text-primary-foreground transition-all hover:bg-primary/90 hover:shadow-lg"
                            >
                                Get Started Free
                            </Link>
                            <Link
                                href={login()}
                                className="inline-flex items-center justify-center rounded-full border border-border bg-card px-8 py-3 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                            >
                                Contact Us
                            </Link>
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="border-t border-border py-12">
                    <div className="container mx-auto px-4 lg:px-8">
                        <div className="grid md:grid-cols-4 gap-8 mb-8">
                            <div>
                                <div className="flex items-center gap-2 mb-4">
                                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                        <SignalIcon />
                                    </div>
                                    <span className="font-bold text-foreground">CivicSignal</span>
                                </div>
                                <p className="text-sm text-muted-foreground mb-2">
                                    Connect the data that shapes power.
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Civic Analytics Infrastructure
                                </p>
                            </div>
                            {[
                                { title: 'Product', links: ['Features', 'Integrations', 'API'] },
                                { title: 'Resources', links: ['Documentation', 'Methodology', 'Blog'] },
                                { title: 'Company', links: ['About', 'Contact', 'Research'] },
                            ].map((col) => (
                                <div key={col.title}>
                                    <h4 className="font-medium text-foreground mb-4">{col.title}</h4>
                                    <ul className="space-y-2">
                                        {col.links.map((link) => (
                                            <li key={link}>
                                                <a href="#" className="text-sm text-muted-foreground hover:text-foreground transition-colors">
                                                    {link}
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </div>
                        <div className="pt-8 border-t border-border flex flex-col md:flex-row items-center justify-between gap-4">
                            <p className="text-sm text-muted-foreground">
                                © {new Date().getFullYear()} CivicSignal. All rights reserved.
                            </p>
                            <div className="flex gap-6">
                                <a href="#" className="text-sm text-muted-foreground hover:text-foreground">Privacy</a>
                                <a href="#" className="text-sm text-muted-foreground hover:text-foreground">Terms</a>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
