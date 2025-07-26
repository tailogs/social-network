-- combine_files.lua
local lfs = require("lfs") -- нужна библиотека LuaFileSystem

-- Рекурсивно собирает файлы с нужными расширениями
local function collectFiles(dir, extensions)
    local files = {}
    for entry in lfs.dir(dir) do
        if entry ~= "." and entry ~= ".." then
            local fullPath = dir .. "/" .. entry
            local attr = lfs.attributes(fullPath)
            if attr.mode == "directory" then
                local subFiles = collectFiles(fullPath, extensions)
                for _, f in ipairs(subFiles) do
                    table.insert(files, f)
                end
            elseif attr.mode == "file" then
                for _, ext in ipairs(extensions) do
                    if fullPath:match("%." .. ext .. "$") then
                        table.insert(files, fullPath)
                        break
                    end
                end
            end
        end
    end
    return files
end

-- Основной код
local extensions = { "php", "js", "css", "html", "txt", "cfg" }
local rootDir = "." -- текущая директория
local allFiles = collectFiles(rootDir, extensions)

-- Выводим список файлов (dir)
local contents = { "-- === dir ===" }
for _, path in ipairs(allFiles) do
    table.insert(contents,(path))
end

-- Добавляем содержимое каждого файла
for _, filePath in ipairs(allFiles) do
    local file = io.open(filePath, "r")
    if file then
        local content = file:read("*all")
        table.insert(contents, string.format("\n\n-- === %s ===\n", filePath))
        table.insert(contents, content)
        file:close()
    else
        print("Error: File not found: " .. filePath)
    end
end

-- Запись в выходной файл
local outputFile = "combined.lua"
local output = io.open(outputFile, "w")
if output then
    output:write(table.concat(contents, "\n"))
    output:close()
    print("Successfully wrote combined code to " .. outputFile)
else
    print("Error: Could not write to " .. outputFile)
end
