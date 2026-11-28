// montage out.png in1.png in2.png ... — side by side, scaled to 1600px wide total
import AppKit
let a = CommandLine.arguments; let imgs = a[2...].map { NSImage(contentsOfFile: $0)! }
let h: CGFloat = 1100; let ws = imgs.map { $0.size.width * h / $0.size.height }
let W = ws.reduce(0, +) + CGFloat(imgs.count - 1) * 12
let out = NSImage(size: NSSize(width: W, height: h)); out.lockFocus(); NSColor(white: 0.6, alpha: 1).setFill(); NSRect(x: 0, y: 0, width: W, height: h).fill()
var x: CGFloat = 0; for (i, im) in imgs.enumerated() { im.draw(in: NSRect(x: x, y: 0, width: ws[i], height: h)); x += ws[i] + 12 }
out.unlockFocus(); let rep = NSBitmapImageRep(data: out.tiffRepresentation!)!
try! rep.representation(using: .png, properties: [:])!.write(to: URL(fileURLWithPath: a[1]))
