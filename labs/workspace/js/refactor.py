import os
import re

directory = '.'

for filename in os.listdir(directory):
    if filename.endswith('.js') and not filename.startswith('refactor'):
        filepath = os.path.join(directory, filename)
        with open(filepath, 'r') as f:
            content = f.read()

        # If it doesn't have the wrapper, we skip because we already wrapped it.
        # Wait, if we already wrapped it, the async functions were NOT exported!
        # So we need to re-parse the original functions and append them to the export block.
        
        # Let's extract the inner content first
        if 'IIFE Error Boundary' in content:
            # Extract content between '"use strict";' and '// --- Explicit Window Exports'
            match = re.search(r'"use strict";(.*?)// --- Explicit Window Exports', content, re.DOTALL)
            if match:
                inner_content = match.group(1)
            else:
                inner_content = content
        else:
            inner_content = content
            
        exports = []
        
        # 1. function xyz(
        funcs = re.findall(r'^function\s+([a-zA-Z0-9_]+)\s*\(', inner_content, re.MULTILINE)
        exports.extend(funcs)
        
        # 2. async function xyz(
        async_funcs = re.findall(r'^async\s+function\s+([a-zA-Z0-9_]+)\s*\(', inner_content, re.MULTILINE)
        exports.extend(async_funcs)
        
        # 3. const/let/var xyz = 
        vars = re.findall(r'^(?:const|let|var)\s+([a-zA-Z0-9_]+)\s*=', inner_content, re.MULTILINE)
        exports.extend(vars)
        
        # Remove duplicates
        exports = list(set(exports))
        
        export_lines = "\n    // --- Explicit Window Exports for Inline HTML ---\n"
        for exp in exports:
            export_lines += f"    window.{exp} = {exp};\n"

        # Wrap content
        new_content = f"""/**
 * Wrapped with IIFE Error Boundary
 */
try {{
  (function() {{
    "use strict";
{inner_content}
{export_lines}
  }})();
}} catch (e) {{
  console.error("[Fatal Error in {filename}]", e);
}}
"""
        with open(filepath, 'w') as f:
            f.write(new_content)
        print(f"Refactored {filename} with exports: {exports}")

