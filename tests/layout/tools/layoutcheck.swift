// layoutcheck <pdf> <headings.txt>
// Pixel-level layout QA for the cleanup report PDF (A4, cover = page 1).
//  OVERFLOW  ink in the side margins of a content page (text/table/image spilling out)
//  HEADING   a section heading in the bottom 18% of a page (stranded heading)
//  GAP       (warning) a non-final page whose content stops above 70% — expected before an unsplittable image
//  STRANDED  a heading that is the last thing on a page with a gap (heading separated from its content)
//  CLIP      ink touching the footer rule zone (content running into the footer)
//  OVERLAP   two lines of text drawn over each other (any page, cover included)
//  OUTSIDE   cover text beyond the page's safe area (cut off at the edge)
//  GLANCE    the "At a glance" summary spilling onto a second page
// Exit code = number of problems.
import PDFKit
import AppKit

let args = CommandLine.arguments
let doc = PDFDocument(url: URL(fileURLWithPath: args[1]))!
// headings file: one "<level>\t<text>" per line (2 = section h2, 3 = h3), in document order.
let headingRows: [(level: Int, text: String)] = ((try? String(contentsOfFile: args[2], encoding: .utf8)) ?? "")
    .split(separator: "\n").compactMap { line in
        let parts = line.split(separator: "\t", maxSplits: 1).map(String.init)
        return parts.count == 2 ? (Int(parts[0]) ?? 3, parts[1]) : (line.isEmpty ? nil : (3, String(line)))
    }
let headings = headingRows.map { $0.text }
let mm: CGFloat = 72 / 25.4
var problems = 0
var gapBottom: [Int: CGFloat] = [:]
func report(_ s: String) { print(s); problems += 1 }

func bitmap(_ page: PDFPage) -> NSBitmapImageRep {
    let r = page.bounds(for: .mediaBox)
    let rep = NSBitmapImageRep(bitmapDataPlanes: nil, pixelsWide: Int(r.width * 2), pixelsHigh: Int(r.height * 2), bitsPerSample: 8, samplesPerPixel: 4, hasAlpha: true, isPlanar: false, colorSpaceName: .deviceRGB, bytesPerRow: 0, bitsPerPixel: 0)!
    NSGraphicsContext.saveGraphicsState()
    let ctx = NSGraphicsContext(bitmapImageRep: rep)!
    NSGraphicsContext.current = ctx
    ctx.cgContext.setFillColor(NSColor.white.cgColor); ctx.cgContext.fill(CGRect(x: 0, y: 0, width: r.width * 2, height: r.height * 2))
    ctx.cgContext.scaleBy(x: 2, y: 2)
    page.draw(with: .mediaBox, to: ctx.cgContext)
    NSGraphicsContext.restoreGraphicsState()
    return rep
}
// Ink = clearly non-white pixel. y measured from the TOP in points.
func inkRows(_ rep: NSBitmapImageRep, x0: CGFloat, x1: CGFloat, y0: CGFloat, y1: CGFloat) -> [CGFloat] {
    var rows: [CGFloat] = []
    let data = rep.bitmapData!; let bpr = rep.bytesPerRow
    for py in Int(y0 * 2)..<Int(y1 * 2) {
        for px in stride(from: Int(x0 * 2), to: Int(x1 * 2), by: 1) {
            let o = py * bpr + px * 4
            if Int(data[o]) < 235 || Int(data[o + 1]) < 235 || Int(data[o + 2]) < 235 { rows.append(CGFloat(py) / 2); break }
        }
    }
    return rows
}

let n = doc.pageCount
for i in 1..<n {                      // skip cover (index 0)
    let page = doc.page(at: i)!
    let r = page.bounds(for: .mediaBox); let W = r.width, H = r.height
    let rep = bitmap(page)
    let top = 52.0, footerRule = H - 50          // below header rule, above footer rule
    let left = 18 * mm, right = W - 18 * mm
    // OVERFLOW: anything in the side margins between header and footer.
    let l = inkRows(rep, x0: 4, x1: left - 3, y0: top, y1: footerRule)
    let rr = inkRows(rep, x0: right + 3, x1: W - 4, y0: top, y1: footerRule)
    if !l.isEmpty || !rr.isEmpty { report("p\(i + 1) OVERFLOW into \(l.isEmpty ? "" : "left ")\(rr.isEmpty ? "" : "right ")margin at y≈\(Int((l + rr).min()!))pt") }
    // Content extent.
    let rows = inkRows(rep, x0: left, x1: right, y0: top, y1: footerRule - 2)
    let usable = (footerRule - 22 * mm) - (24 * mm)
    let bottom = rows.max() ?? top
    // CLIP: content within 3pt of the footer zone.
    if bottom > H - 22 * mm + 3 { report("p\(i + 1) CLIP content reaches y=\(Int(bottom))pt (bottom margin starts \(Int(H - 22 * mm)))") }
    let fill = (bottom - 24 * mm) / usable
    let nextStartsWithBreak = i == 1   // "At a glance" page ends with a forced break
    if i < n - 1 && !nextStartsWithBreak && fill < 0.70 { print(String(format: "  warn p%d GAP content ends at %.0f%% of the page", i + 1, fill * 100)); gapBottom[i] = bottom }
    print(String(format: "  p%-2d fill %3.0f%%", i + 1, min(fill, 1) * 100))
}
// OVERLAP: two lines of text drawn on top of each other (absolute-positioned cover blocks colliding, text
// running under a box). OUTSIDE: text pushed off the cover's safe area (e.g. a long client name). All pages.
for i in 0..<n {
    let page = doc.page(at: i)!
    let r = page.bounds(for: .mediaBox)
    let lines = (page.selection(for: r)?.selectionsByLine() ?? [])
        .map { (text: ($0.string ?? "").trimmingCharacters(in: .whitespacesAndNewlines), box: $0.bounds(for: page)) }
        .filter { !$0.text.isEmpty && $0.box.width > 0.5 && $0.box.height > 0.5 }
    var seen = Set<String>()
    for a in 0..<lines.count {
        for b in (a + 1)..<max(a + 1, lines.count) {
            let x = lines[a].box.intersection(lines[b].box)
            guard !x.isNull, x.width > 1, x.height > 1 else { continue }
            let smaller = min(lines[a].box.width * lines[a].box.height, lines[b].box.width * lines[b].box.height)
            let key = "\(lines[a].text)|\(lines[b].text)"
            if x.width * x.height > 0.25 * smaller, !seen.contains(key) {
                seen.insert(key)
                report("p\(i + 1) OVERLAP \"\(lines[a].text.prefix(40))\" and \"\(lines[b].text.prefix(40))\"")
            }
        }
    }
    if i == 0 {
        let safe = r.insetBy(dx: 12 * mm, dy: 10 * mm)
        for l in lines where !safe.contains(l.box) {
            report("p1 OUTSIDE cover text \"\(l.text.prefix(40))\" goes beyond the page's safe area")
        }
    }
}
// Where each heading first appears in the body (after the cover and the contents list), top of page = 0.
func place(_ h: String) -> (page: Int, y: CGFloat)? {
    for sel in doc.findString(h, withOptions: [.literal]) {
        guard let p = sel.pages.first else { continue }
        let idx = doc.index(for: p); if idx <= 1 { continue }
        let H = p.bounds(for: .mediaBox).height
        return (idx, (H - sel.bounds(for: p).maxY) / H)
    }
    return nil
}
// HEADING: stranded headings.
for (hi, h) in headings.enumerated() {
    for sel in doc.findString(h, withOptions: [.literal]) {
        guard let p = sel.pages.first else { continue }
        let idx = doc.index(for: p); if idx <= 1 { continue }   // cover, and the contents list on "At a glance"
        let b = sel.bounds(for: p); let H = p.bounds(for: .mediaBox).height
        let yTop = H - b.maxY
        if yTop / H > 0.82 { report("p\(idx + 1) HEADING \"\(h)\" at \(Int(yTop / H * 100))% down the page") }
        // Stranded = heading near the bottom of a short page AND its section carries on over the page (the next
        // heading isn't at the top of the next page). A one-line section that simply ends there is fine.
        // The next heading at the same or a higher level (a sub-heading at the top of the next page means the
        // section did carry on).
        let level = headingRows[hi].level
        let nextText = headingRows[(hi + 1)...].first { $0.level <= level }?.text
        let next = nextText.flatMap(place)
        let sectionEndsHere = next.map { $0.page == idx + 1 && $0.y < 0.25 } ?? (idx == n - 1)
        if let gb = gapBottom[idx], gb - yTop < 70, !sectionEndsHere { report("p\(idx + 1) STRANDED \"\(h)\" is the last thing on a page with a gap") }
    }
}
// GLANCE: "At a glance" must fit on page 2, so the first section starts at the top of page 3.
if let first = headingRows.first(where: { $0.level == 2 })?.text {
    let hits = doc.findString(first, withOptions: [.literal]).compactMap { sel -> (Int, CGFloat)? in
        guard let p = sel.pages.first else { return nil }
        let H = p.bounds(for: .mediaBox).height
        return (doc.index(for: p), (H - sel.bounds(for: p).maxY) / H)
    }
    if !hits.contains(where: { $0.0 == 2 && $0.1 < 0.3 }) { report("p2 GLANCE \"At a glance\" runs past one page (first section \"\(first)\" doesn't start page 3)") }
}
print(problems == 0 ? "OK: no layout problems in \(n) pages" : "\(problems) problem(s) in \(n) pages")
exit(Int32(min(problems, 100)))
