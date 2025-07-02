local HttpService = game:GetService("HttpService")
local hwid = game:GetService("RbxAnalyticsService"):GetClientId()
local url = "https://tureplit.repl.co/?hwid=" .. hwid

local success, resultado = pcall(function()
    return HttpService:GetAsync(url)
end)

if success and resultado and resultado ~= "no_autorizado" then
    local menu = loadstring(resultado)
    if menu then
        menu()
    end
else
    warn("No autorizado")
end
