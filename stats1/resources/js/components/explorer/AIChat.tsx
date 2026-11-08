import { useState, useRef, useEffect } from 'react';
import { cn } from '@/lib/utils';

interface Message {
    role: 'user' | 'assistant';
    content: string;
    timestamp: Date;
}

interface AIChatProps {
    filters?: {
        start_date?: string;
        end_date?: string;
        candidate?: string;
        party?: string;
    };
}

export default function AIChat({ filters }: AIChatProps) {
    const [messages, setMessages] = useState<Message[]>([]);
    const [input, setInput] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [suggestions, setSuggestions] = useState<string[]>([]);
    const messagesEndRef = useRef<HTMLDivElement>(null);

    // Fetch suggestions on mount
    useEffect(() => {
        fetchSuggestions();
    }, []);

    // Auto-scroll to bottom
    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    const fetchSuggestions = async () => {
        try {
            const response = await fetch('/ai/suggestions');
            const data = await response.json();
            setSuggestions(data.suggestions || []);
        } catch (error) {
            console.error('Failed to fetch suggestions:', error);
        }
    };

    const handleSubmit = async (query: string) => {
        if (!query.trim() || isLoading) return;

        const userMessage: Message = {
            role: 'user',
            content: query.trim(),
            timestamp: new Date(),
        };

        setMessages((prev) => [...prev, userMessage]);
        setInput('');
        setIsLoading(true);

        try {
            const response = await fetch('/ai/analyze', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    query: query.trim(),
                    filters: filters,
                }),
            });

            const data = await response.json();

            const assistantMessage: Message = {
                role: 'assistant',
                content: data.success ? data.response : `Error: ${data.error || 'Failed to analyze'}`,
                timestamp: new Date(),
            };

            setMessages((prev) => [...prev, assistantMessage]);
        } catch (error) {
            const errorMessage: Message = {
                role: 'assistant',
                content: 'Sorry, I encountered an error. Please try again.',
                timestamp: new Date(),
            };
            setMessages((prev) => [...prev, errorMessage]);
        } finally {
            setIsLoading(false);
        }
    };

    const handleSuggestionClick = (suggestion: string) => {
        handleSubmit(suggestion);
    };

    return (
        <div className="flex flex-col h-[500px] rounded-xl border border-border bg-card overflow-hidden">
            {/* Header */}
            <div className="px-4 py-3 border-b border-border bg-gradient-to-r from-primary/10 to-purple-500/10">
                <div className="flex items-center gap-2">
                    <div className="h-8 w-8 rounded-lg bg-gradient-to-br from-primary to-purple-500 flex items-center justify-center">
                        <svg className="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <h3 className="font-semibold text-foreground text-sm">AI Data Analyst</h3>
                        <p className="text-xs text-muted-foreground">Powered by GPT-4o</p>
                    </div>
                </div>
            </div>

            {/* Messages */}
            <div className="flex-1 overflow-y-auto p-4 space-y-4">
                {messages.length === 0 ? (
                    <div className="h-full flex flex-col items-center justify-center text-center px-4">
                        <div className="h-16 w-16 rounded-full bg-muted/50 flex items-center justify-center mb-4">
                            <svg className="h-8 w-8 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </div>
                        <h4 className="font-medium text-foreground mb-2">Ask me about your data</h4>
                        <p className="text-sm text-muted-foreground mb-6 max-w-sm">
                            I can analyze trends, find correlations, and provide insights on your political and economic data.
                        </p>

                        {/* Suggestions */}
                        {suggestions.length > 0 && (
                            <div className="w-full space-y-2">
                                <p className="text-xs text-muted-foreground font-medium">Try asking:</p>
                                <div className="flex flex-wrap gap-2 justify-center">
                                    {suggestions.slice(0, 4).map((suggestion, i) => (
                                        <button
                                            key={i}
                                            onClick={() => handleSuggestionClick(suggestion)}
                                            className="px-3 py-1.5 text-xs bg-muted rounded-full text-foreground hover:bg-primary/10 hover:text-primary transition-colors"
                                        >
                                            {suggestion}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                ) : (
                    messages.map((message, i) => (
                        <div
                            key={i}
                            className={cn(
                                'flex',
                                message.role === 'user' ? 'justify-end' : 'justify-start'
                            )}
                        >
                            <div
                                className={cn(
                                    'max-w-[85%] rounded-2xl px-4 py-2.5',
                                    message.role === 'user'
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-foreground'
                                )}
                            >
                                <p className="text-sm whitespace-pre-wrap">{message.content}</p>
                                <p className={cn(
                                    'text-[10px] mt-1',
                                    message.role === 'user' ? 'text-primary-foreground/70' : 'text-muted-foreground'
                                )}>
                                    {message.timestamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                </p>
                            </div>
                        </div>
                    ))
                )}

                {/* Loading indicator */}
                {isLoading && (
                    <div className="flex justify-start">
                        <div className="bg-muted rounded-2xl px-4 py-3">
                            <div className="flex items-center gap-1">
                                <div className="h-2 w-2 bg-primary/60 rounded-full animate-bounce" style={{ animationDelay: '0ms' }} />
                                <div className="h-2 w-2 bg-primary/60 rounded-full animate-bounce" style={{ animationDelay: '150ms' }} />
                                <div className="h-2 w-2 bg-primary/60 rounded-full animate-bounce" style={{ animationDelay: '300ms' }} />
                            </div>
                        </div>
                    </div>
                )}

                <div ref={messagesEndRef} />
            </div>

            {/* Input */}
            <div className="p-4 border-t border-border bg-muted/30">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        handleSubmit(input);
                    }}
                    className="flex gap-2"
                >
                    <input
                        type="text"
                        value={input}
                        onChange={(e) => setInput(e.target.value)}
                        placeholder="Ask about trends, correlations, or insights..."
                        className="flex-1 rounded-lg border border-border bg-background px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary placeholder:text-muted-foreground"
                        disabled={isLoading}
                    />
                    <button
                        type="submit"
                        disabled={!input.trim() || isLoading}
                        className="rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    );
}
