class SolutionChecker {
    constructor() {
        this.SIMILARITY_THRESHOLD = 0.99; // 99% similarity required
    }

    // Main function to check if solution is correct
    checkSolution(userCode, expectedOutput, showFeedback = false) {
        const cleanedUserCode = this.cleanHtmlCode(userCode);
        const cleanedExpectedCode = this.cleanHtmlCode(expectedOutput);
        
        const similarity = this.calculateSimilarity(cleanedUserCode, cleanedExpectedCode);
        return similarity >= this.SIMILARITY_THRESHOLD;
    }

    // Clean HTML code by removing unnecessary whitespace and standardizing format
    cleanHtmlCode(input) {
        return input
            // Remove whitespace between tag brackets and tag names
            .replace(/(<\/?)\s+([a-zA-Z0-9]+)/g, '$1$2')
            .replace(/([a-zA-Z0-9]+)\s+>/g, '$1>')
            
            // Remove whitespace within tags (between attribute name and value)
            .replace(/(\w+)\s*=\s*["'](.*?)["']/g, '$1="$2"')
            
            // Remove whitespace between attributes
            .replace(/"\s+(\w+)/g, '" $1')
            
            // Handle self-closing tags
            .replace(/\s*\/>/g, '/>')
            
            // Remove comments
            .replace(/<!--[\s\S]*?-->/g, '')
            
            // Remove multiple spaces to single space
            .replace(/\s+/g, ' ')
            
            // Remove whitespace between tags
            .replace(/>\s+</g, '><')
            
            // Remove unnecessary whitespace at the beginning and end
            .trim()
            
            // Convert to lowercase for case-insensitive comparison
            .toLowerCase();
    }

    // Calculate similarity between two strings using Levenshtein distance
    calculateSimilarity(s1, s2) {
        if (s1 === s2) return 1.0;
        
        const longer = s1.length > s2.length ? s1 : s2;
        const shorter = s1.length > s2.length ? s2 : s1;
        const longerLength = longer.length;
        
        if (longerLength === 0) return 1.0;
        
        const editDistance = this.levenshteinDistance(s1, s2);
        return (longerLength - editDistance) / longerLength;
    }

    // Calculate Levenshtein distance between two strings
    levenshteinDistance(a, b) {
        if (a.length === 0) return b.length;
        if (b.length === 0) return a.length;

        const matrix = Array(b.length + 1).fill(null)
            .map(() => Array(a.length + 1).fill(null));

        for (let i = 0; i <= a.length; i++) matrix[0][i] = i;
        for (let j = 0; j <= b.length; j++) matrix[j][0] = j;

        for (let j = 1; j <= b.length; j++) {
            for (let i = 1; i <= a.length; i++) {
                const indicator = a[i - 1] === b[j - 1] ? 0 : 1;
                matrix[j][i] = Math.min(
                    matrix[j][i - 1] + 1,
                    matrix[j - 1][i] + 1,
                    matrix[j - 1][i - 1] + indicator
                );
            }
        }
        
        return matrix[b.length][a.length];
    }

    // Handle special HTML elements and attributes
    validateSpecialElements(code) {
        // Check for required structure in specific lessons
        const hasDoctype = /<!DOCTYPE html>/i.test(code);
        const hasHtmlTag = /<html>[\s\S]*<\/html>/i.test(code);
        const hasHeadTag = /<head>[\s\S]*<\/head>/i.test(code);
        const hasBodyTag = /<body>[\s\S]*<\/body>/i.test(code);
        
        return {
            doctype: hasDoctype,
            htmlTag: hasHtmlTag,
            headTag: hasHeadTag,
            bodyTag: hasBodyTag
        };
    }

    // Format error messages based on validation results
    formatErrorMessage(validation) {
        const errors = [];
        if (!validation.doctype) errors.push("Missing DOCTYPE declaration");
        if (!validation.htmlTag) errors.push("Missing <html> tags");
        if (!validation.headTag) errors.push("Missing <head> section");
        if (!validation.bodyTag) errors.push("Missing <body> section");
        
        return errors.length > 0 ? errors.join(", ") : null;
    }

    // Check for specific HTML elements presence
    hasElement(code, elementTag) {
        const regex = new RegExp(`<${elementTag}[^>]*>.*?</${elementTag}>`, 'i');
        return regex.test(code);
    }

    // Check if attributes are present and have correct values
    checkAttributes(code, element, attributes) {
        const elementRegex = new RegExp(`<${element}[^>]*>`, 'i');
        const elementMatch = code.match(elementRegex);
        
        if (!elementMatch) return false;
        
        const elementString = elementMatch[0];
        return attributes.every(attr => {
            if (typeof attr === 'string') {
                return new RegExp(`${attr}=["'][^"']*["']`).test(elementString);
            } else if (attr.name && attr.value) {
                return new RegExp(`${attr.name}=["']${attr.value}["']`).test(elementString);
            }
            return false;
        });
    }
}

// Export the class for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SolutionChecker;
} else {
    window.SolutionChecker = SolutionChecker;
}