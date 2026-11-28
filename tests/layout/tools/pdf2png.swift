import PDFKit; import AppKit
let a = CommandLine.arguments; let doc = PDFDocument(url: URL(fileURLWithPath: a[1]))!
let scale: CGFloat = a.count > 3 ? CGFloat(Double(a[3])!) : 1.4
for i in 0..<doc.pageCount { let p = doc.page(at: i)!; let r = p.bounds(for: .mediaBox)
  let img = NSImage(size: NSSize(width: r.width*scale, height: r.height*scale)); img.lockFocus()
  NSColor.white.set(); NSRect(origin: .zero, size: img.size).fill()
  let ctx = NSGraphicsContext.current!.cgContext; ctx.scaleBy(x: scale, y: scale); p.draw(with: .mediaBox, to: ctx); img.unlockFocus()
  let rep = NSBitmapImageRep(data: img.tiffRepresentation!)!
  try! rep.representation(using: .png, properties: [:])!.write(to: URL(fileURLWithPath: "\(a[2])-\(i+1).png")) }
print(doc.pageCount)
