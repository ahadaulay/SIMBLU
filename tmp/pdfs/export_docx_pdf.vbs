Option Explicit

Dim args, inputPath, outputPath, word, document
Set args = WScript.Arguments

If args.Count <> 2 Then
    WScript.Echo "Usage: export_docx_pdf.vbs input.docx output.pdf"
    WScript.Quit 1
End If

inputPath = args(0)
outputPath = args(1)

On Error Resume Next
Set word = CreateObject("Word.Application")
If Err.Number <> 0 Then
    WScript.Echo "Microsoft Word tidak dapat dibuka: " & Err.Description
    WScript.Quit 2
End If

word.Visible = False
word.DisplayAlerts = 0
Set document = word.Documents.Open(inputPath, False, True)
If Err.Number <> 0 Then
    WScript.Echo "Dokumen tidak dapat dibuka: " & Err.Description
    word.Quit
    WScript.Quit 3
End If

document.ExportAsFixedFormat outputPath, 17
If Err.Number <> 0 Then
    WScript.Echo "PDF tidak dapat dibuat: " & Err.Description
    document.Close False
    word.Quit
    WScript.Quit 4
End If

document.Close False
word.Quit
WScript.Echo outputPath
