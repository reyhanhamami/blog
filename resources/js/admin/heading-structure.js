export function headingStructure(headings) {
    let previous = 1; // The public article title is H1.
    const skipped = [];
    for (const heading of headings) {
        if (heading.level > previous + 1) skipped.push({ from: previous, to: heading.level, text: heading.text });
        previous = heading.level;
    }
    return { bodyH1Count: headings.filter(heading => heading.level === 1).length, skipped };
}
